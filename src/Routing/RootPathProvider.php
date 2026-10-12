<?php

namespace FriendsOfRedaxo\Fluid\Routing;

use Redaxo\Core\AbstractProject;
use Redaxo\Core\Filesystem\DefaultPathProvider;

/**
 * Adressen ab dem Basis-Pfad der Website (/assets/…, /media/…, /index.php) statt relativ (./assets/…).
 * Nötig für schöne Adressen: Auf /leistungen/beratung würden relative Adressen sonst unter /leistungen/ gesucht.
 * Router::boot setzt ihn im Frontend über Url::init() (Erweiterungspunkt des Cores).
 */
final class RootPathProvider extends DefaultPathProvider
{
    public function __construct(AbstractProject $project, string $basePath)
    {
        $this->base = rtrim($basePath, '/') . '/';
        $this->frontend = '';
        $this->backend = $this->base . $project->backendDirectory . '/';
        $this->core = $this->base;
        $this->provideAbsolutes = false;
    }
}
