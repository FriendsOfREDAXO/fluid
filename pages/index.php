<?php

use Redaxo\Core\Backend\Controller;
use Redaxo\Core\View\View;

echo View::title('fluid', 'Bearbeiten auf der Website');
Controller::includeCurrentPageSubPath();
