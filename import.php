#!/usr/bin/php -q
<?php
require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';

// backup("site");

if (!file_exists(FILE_IMPORTLOG)){
    
    updateLog(array('TIMESTAMP','SKU','WCPRODUCTID','WCIMGID','FILE','MD5'));
    
}
// Load SKUs
$sql_result_sku = $mysqli->query('SELECT po.ID AS id, pml.sku FROM wp_posts po, wp_wc_product_meta_lookup pml WHERE pml.product_id=po.ID AND po.post_status="publish" GROUP BY pml.sku ORDER BY pml.sku DESC');

while($sql_data_sku = $sql_result_sku->fetch_array(MYSQLI_ASSOC)) {
    $arr_sku[$sql_data_sku['id']] = $sql_data_sku['sku'];
}

// DEBUG
// echo "===== STANDBY: RESETTING ENV =====" . PHP_EOL;
// $dbrestore = "sudo mysql ". DB_NAME." < " . DIR_SITE . "/20210721-100814-". DB_NAME."-db.sql";
// shell_exec($dbrestore);
// reset_uploads("101074");
// $arr_sku = [ 14013 => 101074 ];
// die;

echo "" . PHP_EOL;
echo "================================" . PHP_EOL;
echo "START - " . date("Y-m-d H:i:s") . PHP_EOL;
echo "" . PHP_EOL;

foreach ($arr_sku as $woo_product_id => $sku){
    unset($ProductImage);
    $ProductImage=array();
    // Load List of Images based on sku
    $arr_files=glob( DIR_IMAGES . "/" . $sku . "*.{jpg,JPG,jpeg,JPEG,png,PNG,gif,GIF}", GLOB_BRACE);
    asort($arr_files);

    foreach ($arr_files as $file){
        unset($ProductImage);
        $ProductImage=array();

        $file_epoch = filemtime($file);
        $file_date = new DateTime("@$file_epoch"); 
        $ProductImage['WOO_PRODUCT_ID'] = $woo_product_id;
        $ProductImage['SKU'] = $sku;
        $ProductImage['FILE'] = $file;
        $ProductImage['FILE_BASE'] = basename($file);
        $ProductImage['FILE_EPOCH'] = filemtime($file);
        $ProductImage['FILE_DATE'] = $file_date->format('Y-m-d H:i:s');
        $ProductImage['FILE_MD5'] = md5_file($file);
        $ProductImage['IMPORTLOG'] = is_imported($ProductImage);

        if ($ProductImage['IMPORTLOG']['Import']){
            
            import($ProductImage);

        }

    }
    
}

echo "END - " . date("Y-m-d H:i:s") . PHP_EOL;

// - FIN -

