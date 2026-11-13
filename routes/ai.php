<?php

use App\Mcp\Servers\RibbonAdminServer;
use Laravel\Mcp\Facades\Mcp;

// Owner-only admin tools for Claude Desktop. LOCAL only (stdio), started over
// SSH: `php artisan mcp:start ribbon`. Deliberately no Mcp::web() route, so
// nothing is reachable from the internet. Acts as MCP_ADMIN_EMAIL (Super Admin).
Mcp::local('ribbon', RibbonAdminServer::class);
