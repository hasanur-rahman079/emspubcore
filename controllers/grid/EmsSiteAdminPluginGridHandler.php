<?php

/**
 * @file plugins/generic/emspubcore/controllers/grid/EmsSiteAdminPluginGridHandler.php
 *
 * Extends SettingsPluginGridHandler to restrict plugin management
 * to OJS site administrators only.
 */

namespace APP\plugins\generic\emspubcore\controllers\grid;

use APP\controllers\grid\settings\plugins\SettingsPluginGridHandler;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;

class EmsSiteAdminPluginGridHandler extends SettingsPluginGridHandler
{
    /**
     * @copydoc GridHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        // Verify user is logged in
        $user = $request->getUser();
        if (!$user) {
            return false;
        }

        // Verify user is a site administrator
        $roleDao = \PKP\db\DAORegistry::getDAO('RoleDAO');
        if (!$roleDao->userHasRole(null, $user->getId(), Role::ROLE_ID_SITE_ADMIN)) {
            return false;
        }

        // Add UserRolesRequiredPolicy to populate ASSOC_TYPE_USER_ROLES and
        // ASSOC_TYPE_USER_GROUP in the authorized context, which are needed by
        // loadCategoryData() and other inherited grid methods.
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        // We have already verified site admin role above, so mark role
        // assignments as checked to satisfy PKPHandler::authorize()'s final check.
        $this->markRoleAssignmentsChecked();

        // Evaluate the minimal policy set (UserRolesRequiredPolicy) and return.
        // This bypasses SettingsPluginGridHandler::authorize()'s ContextAccessPolicy /
        // CanAccessSettingsPolicy chain which causes a fatal error on PHP 8.3 in production.
        $decision = $this->_authorizationDecisionManager->decide();

        return $decision === AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}