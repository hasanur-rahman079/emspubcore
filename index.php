<?php

/**
 * @file plugins/generic/emspubcore/index.php
 *
 * Copyright (c) 2024 EmsPub
 * Distributed under the GNU GPL v3.
 *
 * @brief Wrapper for EmsPubCore plugin.
 *
 */

require_once('EmsPubCorePlugin.php');

return new \APP\plugins\generic\emspubcore\EmsPubCorePlugin();
