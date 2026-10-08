<?php

namespace FriendsOfRedaxo\Blocks;

use RuntimeException;

/** Fehler eines Editor-Endpunkts: Meldung für die Redaktion, Code = HTTP-Status */
final class ApiError extends RuntimeException {}
