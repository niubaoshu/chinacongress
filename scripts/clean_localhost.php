<?php
// 本地 localhost 完全清空脚本 (清空 /srv/http/my_site_name 目录及数据库)
$web_root = '/srv/http/my_site_name';

echo "1. 正在清空本地区域数据库 (chinacongress) 所有数据表...\n";
$mysqli = new mysqli( 'localhost', 'chinacongress', '', 'chinacongress' );
if ( $mysqli->connect_error ) {
    $mysqli = new mysqli( 'localhost', 'root', '', 'chinacongress' );
}

if ( ! $mysqli->connect_error ) {
    $mysqli->query( 'SET FOREIGN_KEY_CHECKS = 0' );
    $res = $mysqli->query( 'SHOW TABLES' );
    while ( $row = $res->fetch_array() ) {
        $mysqli->query( 'DROP TABLE IF EXISTS `' . $row[0] . '`' );
    }
    $mysqli->query( 'SET FOREIGN_KEY_CHECKS = 1' );
    echo "✅ 数据库表已全部清空。\n";
} else {
    echo "⚠️ 数据库连接未成功，跳过清库步骤。\n";
}

echo "2. 正在清空本地 Web 目录: {$web_root} ...\n";
if ( is_dir( $web_root ) ) {
    exec( "rm -rf {$web_root}/* {$web_root}/.* 2>/dev/null || true" );
    echo "✅ 本地 Web 目录包含的所有文件已全部彻底清除！\n";
}

echo "==========================================";
echo "🧹 本地环境彻底清空完毕！\n";
echo "==========================================";
