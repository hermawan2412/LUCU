<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin');

layout_header('Riwayat Pembaruan', '', 'admin');
changelog_render_html();
layout_footer();
