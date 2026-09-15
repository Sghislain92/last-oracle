<?php
require_once __DIR__ . '/_bootstrap.php';
$user = requirePortalUser();
portalJson(200, ['ok' => true, 'user' => ['id' => $user['id'], 'email' => $user['email'], 'companyName' => $user['company_name'], 'contactName' => $user['contact_name'], 'createdAt' => $user['created_at']]]);
