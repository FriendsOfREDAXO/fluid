<?php

namespace FriendsOfRedaxo\Fluid\Routing;

/** Rolle einer Domain (siehe Domains) */
enum DomainRole: string
{
    case Primary = 'primary';
    case Alias = 'alias';
    case Landing = 'landing';
}
