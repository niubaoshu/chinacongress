<?php
// 本地 localhost 网页文件与数据库全量一键归零清空脚本
$dir = '/srv/http/my_site_name';

function rrmdir( $src ) {
    if ( ! file_exists( $src ) ) return;
    $dir = opendir( $src );
    while ( false !== ( $file = readdir( $dir ) ) ) {
        if ( ( $file != '.' ) && ( $file != '..' ) ) {
            $full = $src . '/' . $file;
            if ( is_dir( $full ) ) {
                rrmdir( $full );
            } else {
                @unlink( $full );
            }
        }
    }
    closedir( $dir );
    @rmdir( $src );
}

if ( is_dir( $dir ) ) {
    $files = array_diff( scandir( $dir ), array( '.', '..' ) );
    foreach ( $files as $file ) {
        $path = $dir . '/' . $file;
        if ( is_dir( $path ) ) {
            rrmdir( $path );
        } else {
            @unlink( $path );
        }
    }
}

// 清空本地数据库
require_once '/home/niubaoshu/work/chinacongress/chinacongress.net/public_html/wp-config.php';
$mysqli = new mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );
if ( ! $mysqli->connect_error ) {
    $result = $mysqli->query( 'SHOW TABLES' );
    $tables = array();
    while ( $row = $result->fetch_row() ) {
        $tables[] = $row[0];
    }
    $mysqli->query( 'SET FOREIGN_KEY_CHECKS = 0' );
    foreach ( $tables as $table ) {
        $mysqli->query( 'DROP TABLE IF EXISTS `' . $table . '`' );
    }
    $mysqli->query( 'SET FOREIGN_KEY_CHECKS = 1' );
}

echo "✅ 本地 localhost 网页文件及数据库已成功 100% 清空归零！\n";
