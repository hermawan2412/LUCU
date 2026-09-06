<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('User', 'Pengelola');

layout_header('Riwayat Pembaruan', '');
changelog_render_html();
layout_footer();
