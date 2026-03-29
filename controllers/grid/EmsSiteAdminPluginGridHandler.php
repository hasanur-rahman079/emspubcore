<?php

/**
 * @file plugins/generic/emspubcore/controllers/grid/EmsSiteAdminPluginGridHandler.php
 *
 * Extends SettingsPluginGridHandler to restrict plugin management
 * to OJS site administrators only.
 */

namespace APP\plugins\generic\emspubcore\controllers\grid;

use APP\controllers\grid\settings\plugins\SettingsPluginGridHandler;
use APP\core\Application;
use PKP\security\Role;
use PKP\security\Validation;

class EmsSiteAdminPluginGridHandler extends SettingsPluginGridHandler
{
    /**
     * @copydoc GridHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $user = $request->getUser();
        if (!$user) {
            return false;
        }

        $roleDao = \PKP\db\DAORegistry::getDAO('RoleDAO');
        if (!$roleDao->userHasRole(null, $user->getId(), Role::ROLE_ID_SITE_ADMIN)) {
            return false;
        }

        return parent::authorize($request, $args, $roleAssignments);
    }
}
