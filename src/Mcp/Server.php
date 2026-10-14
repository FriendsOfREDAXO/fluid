<?php

namespace FriendsOfRedaxo\Fluid\Mcp;

use FriendsOfRedaxo\Fluid\ApiError;
use FriendsOfRedaxo\Fluid\Editing;
use FriendsOfRedaxo\Fluid\FluidAddon;
use Redaxo\Core\Core;
use Redaxo\Core\Filesystem\Url;
use stdClass;
use Throwable;

use function in_array;
use function is_array;

/**
 * MCP-Server (Model Context Protocol) über „Streamable HTTP“ – zustandslos, JSON-Antworten (wie KLXM Studio).
 *
 * Endpunkt: POST {website}/fluid-mcp (ohne schöne Adressen: /index.php?fluid_mcp=1)
 * Anmeldung: `Authorization: Bearer fluid_…` (Backend → AddOns → fluid → MCP). Ohne Schlüssel nur, wenn der Admin den
 * öffentlichen Zugang eingeschaltet hat – dann ausschließlich veröffentlichte, freigegebene Inhalte und nur lesen.
 */
final class Server
{
    public const string PATH = 'fluid-mcp';
    public const string PARAM = 'fluid_mcp';

    private const array VERSIONS = ['2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05'];

    /** Anfragen je 5 Minuten: Schlüssel bzw. öffentlich je IP */
    private const int LIMIT_TOKEN = 300;
    private const int LIMIT_PUBLIC = 60;
    private const int WINDOW = 300;

    private Access $access;

    private Tools $tools;

    public static function url(): string
    {
        return Url::absoluteBase(self::PATH);
    }

    public static function handle(): never
    {
        (new self())->run();
        exit;
    }

    private function run(): void
    {
        $cfg = Access::config();
        if (!$cfg['enabled']) {
            self::send(['error' => 'not found'], 404);
        }
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ('OPTIONS' === $method || 'GET' === $method || 'DELETE' === $method) {
            // kein SSE-Stream, keine Sitzungen: zustandsloser Server
            header('Allow: POST');
            self::send(['error' => 'Nur POST (MCP Streamable HTTP, JSON-Antworten).'], 405);
        }
        // Schutz vor DNS-Rebinding: fremde Browser-Origins ablehnen
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ('' !== $origin && parse_url($origin, PHP_URL_HOST) !== parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
            self::rpcError(403, -32000, 'Origin nicht erlaubt.');
        }

        $bearer = self::bearer();
        if (null !== $bearer) {
            $token = Tokens::check($bearer);
            if (null === $token) {
                header('WWW-Authenticate: Bearer realm="fluid-mcp", error="invalid_token"');
                self::rpcError(401, -32001, 'Schlüssel ungültig, abgelaufen oder Benutzer gesperrt.');
            }
            $this->access = Access::forToken($token);
            self::limit('t' . $token['id'], self::LIMIT_TOKEN);
            // Protokoll, Versionen und Rechte laufen im Namen des Benutzers
            Core::setUser($token['user']);
            Editing::actAs($token['user']);
        } elseif ($cfg['public']) {
            $this->access = Access::anonymous();
            self::limit('ip' . hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '')), self::LIMIT_PUBLIC);
        } else {
            header('WWW-Authenticate: Bearer realm="fluid-mcp"');
            self::rpcError(401, -32001, 'Schlüssel nötig: Authorization: Bearer fluid_…');
        }
        $this->tools = new Tools($this->access);

        $raw = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($raw)) {
            self::send(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'Parse error']]);
        }
        $batch = array_is_list($raw);
        $out = [];
        foreach ($batch ? $raw : [$raw] as $msg) {
            $res = $this->dispatch(is_array($msg) ? $msg : [], $this->access->tokenId ?? 0);
            if (null !== $res) {
                $out[] = $res;
            }
        }
        if ([] === $out) {
            http_response_code(202);
            exit;
        }
        self::send($batch ? $out : $out[0]);
    }

    /** @return array<string, mixed>|null */
    private function dispatch(array $m, int $tokenId): ?array
    {
        $id = $m['id'] ?? null;
        $method = (string) ($m['method'] ?? '');
        $params = is_array($m['params'] ?? null) ? $m['params'] : [];
        $notification = !array_key_exists('id', $m);
        if ('2.0' !== ($m['jsonrpc'] ?? '') || '' === $method) {
            return $notification ? null : ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => -32600, 'message' => 'Invalid Request']];
        }
        if (str_starts_with($method, 'notifications/')) {
            return null;
        }
        try {
            $result = match ($method) {
                'initialize' => $this->initialize($params, $tokenId),
                'ping', 'logging/setLevel' => new stdClass(),
                'tools/list' => ['tools' => array_values(array_map(static fn (array $t) => array_diff_key($t, ['run' => 1]), $this->tools->all()))],
                'tools/call' => $this->call($params),
                'resources/list' => ['resources' => []],
                'resources/templates/list' => ['resourceTemplates' => []],
                'prompts/list' => ['prompts' => []],
                default => throw new \DomainException('Methode „' . $method . '“ nicht gefunden', -32601),
            };

            return $notification ? null : ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
        } catch (\DomainException $e) {
            return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $e->getCode() ?: -32602, 'message' => $e->getMessage()]];
        } catch (Throwable $e) {
            \Redaxo\Core\Log\Logger::logException($e);

            return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => -32603, 'message' => 'Interner Fehler']];
        }
    }

    /** @return array<string, mixed> */
    private function initialize(array $p, int $tokenId): array
    {
        $client = trim(mb_substr(strip_tags((string) ($p['clientInfo']['name'] ?? '') . ' ' . (string) ($p['clientInfo']['version'] ?? '')), 0, 120));
        if ($tokenId > 0 && '' !== $client) {
            Tokens::rememberClient($tokenId, $client);
        }
        $req = (string) ($p['protocolVersion'] ?? '');
        $extra = trim((string) Access::config()['instructions']);
        $public = $this->access->isPublic();

        return [
            'protocolVersion' => in_array($req, self::VERSIONS, true) ? $req : self::VERSIONS[1],
            'capabilities' => ['tools' => ['listChanged' => false], 'resources' => ['listChanged' => false, 'subscribe' => false], 'prompts' => ['listChanged' => false], 'logging' => new stdClass()],
            'serverInfo' => ['name' => 'fluid', 'title' => 'fluid für REDAXO', 'version' => FluidAddon::instance()->getVersion(), 'websiteUrl' => Url::absoluteBase()],
            'instructions' => 'Website mit REDAXO und fluid (' . Url::absoluteBase() . ").\n"
                . "- Starte mit site_overview.\n"
                . ($public
                    ? "- Öffentlicher Zugang: nur veröffentlichte Inhalte, nur lesen.\n"
                    : "- Seiten bestehen aus Blöcken (list_block_types). Änderungen landen im ENTWURF; live geht erst publish_page (falls erlaubt) oder ein Mensch auf der Website. Vorher nachfragen.\n"
                      . "- Rich-Text als HTML ohne Bilder; Bilder nur über Medienfelder (Dateiname aus dem Medienpool).\n"
                      . "- Erfinde keine Fakten.\n")
                . ('' !== $extra ? '- ' . $extra . "\n" : '')
                . 'Zugang: ' . $this->access->describe() . '.',
        ];
    }

    /** @return array<string, mixed> */
    private function call(array $p): array
    {
        $name = (string) ($p['name'] ?? '');
        $tools = $this->tools->all();
        if (!isset($tools[$name])) {
            $why = match (true) {
                $this->access->isPublic() => ' (öffentlich nicht freigegeben)',
                !$this->access->write => ' (dieser Zugang darf nur lesen)',
                default => '',
            };
            throw new \DomainException('Unbekanntes Werkzeug „' . $name . '“' . $why, -32602);
        }
        try {
            $result = ($tools[$name]['run'])(is_array($p['arguments'] ?? null) ? $p['arguments'] : []);
        } catch (ApiError $e) {
            return ['content' => [['type' => 'text', 'text' => $e->getMessage()]], 'isError' => true];
        }

        return ['content' => [['type' => 'text', 'text' => (string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)]],
            'structuredContent' => is_array($result) && !array_is_list($result) ? $result : ['items' => $result]];
    }

    private static function bearer(): ?string
    {
        $h = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ('' === $h && function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) {
                if (0 === strcasecmp((string) $k, 'Authorization')) {
                    $h = (string) $v;
                }
            }
        }
        if (1 === preg_match('~^Bearer\s+(\S+)$~i', trim($h), $m)) {
            return $m[1];
        }
        $key = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''));

        return '' !== $key ? $key : null;
    }

    /** Einfache Begrenzung je Schlüssel bzw. IP (Datei im Cache des AddOns) */
    private static function limit(string $key, int $max): void
    {
        $dir = FluidAddon::instance()->getCachePath('mcp');
        if (!is_dir($dir)) {
            @mkdir($dir, 0o775, true);
        }
        $file = $dir . '/' . $key . '.json';
        $now = time();
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (!is_array($data) || ($data['start'] ?? 0) < $now - self::WINDOW) {
            $data = ['start' => $now, 'n' => 0];
        }
        if (++$data['n'] > $max) {
            header('Retry-After: ' . max(1, $data['start'] + self::WINDOW - $now));
            self::rpcError(429, -32002, 'Zu viele Anfragen – bitte kurz warten.');
        }
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }

    private static function rpcError(int $status, int $code, string $message): never
    {
        self::send(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => $code, 'message' => $message]], $status);
    }

    private static function send(mixed $data, int $status = 200): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
