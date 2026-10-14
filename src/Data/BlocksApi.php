<?php

namespace FriendsOfRedaxo\Fluid\Data;

use FriendsOfRedaxo\Fluid\BlockData;
use FriendsOfRedaxo\Fluid\Form\FieldForm;
use Override;
use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Content\ModulePermission;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\BackendLogin;
use Throwable;

/**
 * YForm-Feld „Fluid-Blöcke“ in der Maske: Formular eines Blocks laden (action=form) und die Eingaben in Blockdaten
 * umwandeln (action=parse) – dieselben Felder wie auf der Website (FieldForm). Nur mit Backend-Sitzung und Modulrecht.
 */
#[AsApiFunction('fluid_blocks')]
final class BlocksApi extends ApiFunction
{
    #[Override]
    public function execute(): Result
    {
        try {
            $user = BackendLogin::createUser() ?? throw new \RuntimeException('Bitte im Backend anmelden.', 403);
            $key = Request::post('module', 'string', '');
            $module = BlockData::module($key) ?? throw new \RuntimeException('Unbekannter Block.', 404);
            $perm = $user->getComplexPerm('modules');
            if (!$user->admin && (!$perm instanceof ModulePermission || !$perm->hasPerm($key))) {
                throw new \RuntimeException('Keine Berechtigung für diesen Block.', 403);
            }
            if ('parse' === Request::post('action', 'string', 'form')) {
                $f = Request::post('fb', 'array', []);
                $data = self::clean(\is_array($f[1] ?? null) ? $f[1] : []);
                $tunes = self::clean(\is_array($f[2] ?? null) ? $f[2] : []);
                $item = ['module' => $key, 'data' => FieldForm::withDefaults($module->fields(), $data, $module->variants()), 'tunes' => $tunes];
                self::send(['ok' => true, 'item' => $item, 'label' => BlockData::label($item)]);
            }
            $data = json_decode(Request::post('data', 'string', ''), true);
            $tunes = json_decode(Request::post('tunes', 'string', ''), true);
            self::send(['ok' => true, 'label' => (string) ($module->definition()['label'] ?? $module->name),
                'html' => FieldForm::render($module, \is_array($data) ? $data : [], \is_array($tunes) ? $tunes : [], 'fb', true)]);
        } catch (Throwable $e) {
            $code = (int) $e->getCode();
            self::send(['ok' => false, 'error' => $e->getMessage()], $code >= 400 && $code < 600 ? $code : 400);
        }
    }

    /** Listen aus Formularen (0,1,2 …) wieder zu Listen machen */
    private static function clean(array $a): array
    {
        foreach ($a as $k => $v) {
            if (\is_array($v)) {
                $v = self::clean($v);
                $a[$k] = [] !== $v && array_filter(array_keys($v), is_int(...)) === array_keys($v) ? array_values($v) : $v;
            }
        }

        return $a;
    }

    private static function send(array $data, int $status = 200): never
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code($status);
        Response::sendJson($data);
        exit;
    }
}
