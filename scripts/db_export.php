<?php
// PHP CLI 数据库一键导出脚本 (免 pwd/socket 依赖)
$wp_config = '/srv/http/my_site_name/wp-config.php';
if ( ! file_exists( $wp_config ) ) {
    $wp_config = '/home/niubaoshu/work/chinacongress/chinacongress.net/public_html/wp-config.php';
}

require_once $wp_config;

$target_file = isset( $argv[1] ) ? $argv[1] : '/tmp/db_export.sql';

$mysqli = new mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );
if ( $mysqli->connect_error ) {
    die( "❌ 数据库连接失败: " . $mysqli->connect_error . "\n" );
}

$mysqli->set_charset( 'utf8mb4' );

$tables = array();
$result = $mysqli->query( 'SHOW TABLES' );
while ( $row = $result->fetch_row() ) {
    $tables[] = $row[0];
}

$return = "-- ChinaCongress Pure Data SQL Dump\n";
$return .= "-- Generated at: " . date( 'Y-m-d H:i:s' ) . "\n\n";

foreach ( $tables as $table ) {
    $result     = $mysqli->query( 'SELECT * FROM ' . $table );
    $num_fields = $result->field_count;

    $return .= 'DROP TABLE IF EXISTS `' . $table . '`;';
    $row2    = $mysqli->query( 'SHOW CREATE TABLE `' . $table . '`' )->fetch_row();
    $return .= "\n\n" . $row2[1] . ";\n\n";

    while ( $row = $result->fetch_row() ) {
        $return .= 'INSERT INTO `' . $table . '` VALUES(';
        for ( $j = 0; $j < $num_fields; $j++ ) {
            if ( isset( $row[$j] ) ) {
                $return .= '"' . $mysqli->real_escape_string( $row[$j] ) . '"';
            } else {
                $return .= 'NULL';
            }
            if ( $j < ( $num_fields - 1 ) ) {
                $return .= ',';
            }
        }
        $return .= ");\n";
    }
    $return .= "\n\n";
}

file_put_contents( $target_file, $return );
echo "✅ 数据库一键成功导出至: {$target_file} (文件大小: " . round( filesize( $target_file ) / 1024, 2 ) . " KB)\n";
