<?php

namespace FriendsOfRedaxo\Fluid\Media;

/**
 * ffmpeg/ffprobe aufrufen (Vorschaubilder von Videos). Ohne ffmpeg bleibt alles wirkungslos – Videos zeigen dann das
 * gewählte Vorschaubild oder keines.
 *
 * Pfad: Umgebungsvariable FFMPEG_BIN bzw. FFPROBE_BIN (z. B. in .env), sonst Suche in PATH und üblichen Ordnern.
 */
final class Ffmpeg
{
    private const array DIRS = ['/usr/bin', '/usr/local/bin', '/opt/homebrew/bin', '/opt/local/bin'];

    /** @var array<string, string> */
    private static array $bins = [];

    public static function bin(string $name = 'ffmpeg'): string
    {
        if (isset(self::$bins[$name])) {
            return self::$bins[$name];
        }
        $env = getenv(strtoupper($name) . '_BIN') ?: ($_SERVER[strtoupper($name) . '_BIN'] ?? '');
        if (\is_string($env) && '' !== $env) {
            return self::$bins[$name] = is_executable($env) ? $env : '';
        }
        if (!\function_exists('proc_open')) {
            return self::$bins[$name] = '';
        }
        foreach ([...explode(PATH_SEPARATOR, (string) getenv('PATH')), ...self::DIRS] as $dir) {
            $f = rtrim($dir, '/') . '/' . $name;
            if ('' !== $dir && @is_file($f) && @is_executable($f)) {
                return self::$bins[$name] = $f;
            }
        }

        return self::$bins[$name] = '';
    }

    public static function available(): bool
    {
        return '' !== self::bin('ffmpeg') && '' !== self::bin('ffprobe');
    }

    /**
     * Befehl ohne Shell ausführen (Argumente als Liste), mit Zeitlimit.
     *
     * @param list<string> $argv
     * @return array{code: int, out: string}
     */
    public static function run(array $argv, int $timeout = 15): array
    {
        $p = @proc_open($argv, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!\is_resource($p)) {
            return ['code' => -1, 'out' => ''];
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = '';
        $end = microtime(true) + $timeout;
        while (true) {
            $out .= (string) stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            $st = proc_get_status($p);
            if (!$st['running']) {
                $out .= (string) stream_get_contents($pipes[1]);
                break;
            }
            if (microtime(true) > $end) {
                proc_terminate($p, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($p);

                return ['code' => -1, 'out' => ''];
            }
            usleep(20_000);
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($p);

        return ['code' => (int) $st['exitcode'], 'out' => $out];
    }

    /** Laufzeit in Sekunden (0 = unbekannt) */
    public static function duration(string $file): float
    {
        $r = self::run([self::bin('ffprobe'), '-v', 'error', '-show_entries', 'format=duration', '-of', 'default=nw=1:nk=1', $file], 10);

        return 0 === $r['code'] ? max(0.0, (float) trim($r['out'])) : 0.0;
    }

    /**
     * Typisches Standbild als JPEG: bei ~10 % der Laufzeit (mindestens 1 s, höchstens 10 s); der Filter „thumbnail“ wählt
     * unter den folgenden Bildern das typischste (überspringt Schwarzblenden). Gibt den Pfad der Datei zurück oder null.
     */
    public static function frame(string $video, string $target, int $maxWidth = 1600): ?string
    {
        if (!self::available() || !is_file($video)) {
            return null;
        }
        $d = self::duration($video);
        $at = $d > 0 ? min(10.0, max(1.0, $d * 0.1), max(0.0, $d - 0.5)) : 1.0;
        $r = self::run([self::bin('ffmpeg'), '-nostdin', '-v', 'error', '-y', '-ss', \sprintf('%.2F', $at), '-i', $video,
            '-vf', 'thumbnail=24,scale=\'min(' . $maxWidth . ',iw)\':-2', '-frames:v', '1', '-q:v', '3', $target], 20);
        if (0 !== $r['code'] || !is_file($target) || 0 === filesize($target)) {
            // sehr kurze Videos: erstes Bild
            $r = self::run([self::bin('ffmpeg'), '-nostdin', '-v', 'error', '-y', '-i', $video, '-frames:v', '1', '-q:v', '3', $target], 20);
        }

        return 0 === $r['code'] && is_file($target) && filesize($target) > 0 ? $target : null;
    }
}
