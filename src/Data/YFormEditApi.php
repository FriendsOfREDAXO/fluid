<?php

namespace FriendsOfRedaxo\Fluid\Data;

use Override;
use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Core;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\CsrfToken;
use Yakamara\YForm\Manager\Table\Table;

/**
 * Von der Website (Datenliste) zur YForm-Maske im Backend: YForm verlangt für func=edit/add ein CSRF-Token, das nur im
 * Backend gilt. Diese Funktion läuft im Backend (Seite yform/manager/data_edit, also mit deren Rechteprüfung) und leitet
 * mit frischem Token weiter. Sie ändert nichts – Speichern schützt YForm selbst.
 */
#[AsApiFunction('fluid_yform_edit')]
final class YFormEditApi extends ApiFunction
{
    protected bool $requiresCsrfProtection = false;

    #[Override]
    public function execute(): Result
    {
        $table = Table::get(Request::get('table_name', 'string', ''));
        $user = Core::getUser();
        if (!$table || !$user || !$table->isGranted('EDIT', $user)) {
            return new Result(false, 'Keine Berechtigung für diese Tabelle.');
        }
        $id = Request::get('data_id', 'int', 0);
        Response::sendRedirect(Url::backendPage('yform/manager/data_edit', ['table_name' => $table->getTableName()]
            + ($id > 0 ? ['data_id' => $id, 'func' => 'edit'] : ['func' => 'add'])
            + CsrfToken::factory($table->getCSRFKey())->getUrlParams()));
    }
}
