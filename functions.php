<?php

function print_var_name($var) {
    foreach($GLOBALS as $var_name => $value) {
        if ($value === $var) {
            return $var_name;
        }
    }

    return false;
}

function debug($value,$die=""){

    echo print_var_name($value) . PHP_EOL;
    echo "" . PHP_EOL;
    print_r($value) . PHP_EOL;
    echo "" . PHP_EOL;
    if ($die=='die'){
        die;
    };

}

/**
 * fields: timestamp,sku,wcproductid,wcimgid,file,md5
 */
function updateLog ($fields) {
    $log = fopen( FILE_IMPORTLOG , 'a' );
    fputcsv($log, $fields);
    fclose($log);
 }


function is_imported($ProductImage) {
    
    global $mysqli;   
    $found_in_db = array();

    $sql_query_attachments = 'SELECT id,post_title,guid,post_date,post_modified FROM wp_posts WHERE post_type = "attachment" and post_parent="' . $ProductImage['WOO_PRODUCT_ID'] . '" ORDER BY post_title';

    $sql_result_attachments = $mysqli->query($sql_query_attachments);

    while($sql_data_attachments = $sql_result_attachments->fetch_array(MYSQLI_ASSOC)) {

        $found_in_db[] = [
            "id" => $sql_data_attachments['id'],
            "name" => $sql_data_attachments['post_title'],
            "url" => $sql_data_attachments['guid'],
            "post_date" => $sql_data_attachments['post_date'],
            "post_modified" => $sql_data_attachments['post_modified']
        ];

    }

    $result['DBITEMS'] = $found_in_db;

    // # import log csv to associative array
    $csv_data = array_map('str_getcsv', file(FILE_IMPORTLOG));
    $csv_header = $csv_data[0];
    unset($csv_data[0]);

    $arr_ImportLog = array();
    foreach ($csv_data as $row) {
        $row = array_combine($csv_header, $row); // adds header to each row as key

        $arr_ImportLog[] = array(
            'TIMESTAMP' => trim($row['TIMESTAMP']),
            'SKU' => trim($row['SKU']),
            'WOOPRODUCTID' => trim($row['WCPRODUCTID']),
            'WPIMGID' => trim($row['WCIMGID']),
            'FILE' => trim($row['FILE']),
            'MD5' => trim($row['MD5'])
        );
    }

    if (file_exists($ProductImage['FILE'])) {

        $imported = array();

        foreach ($arr_ImportLog as $import) {

            if ($import['FILE'] == $ProductImage['FILE_BASE']) {

                $imported[] = array(
                    'file' => $import['FILE'],
                    'md5' => $import['MD5'],
                    'timestamp' => $import['TIMESTAMP'],
                    'epoch' => strtotime($import['TIMESTAMP']),
                    'sku' => $import['SKU'],
                    'wooproductid' => $import['WOOPRODUCTID'],
                    'wpimgid' => $import['WPIMGID']
                );
            }
        }
            
        $result['LOGITEMS'] = $imported;

        if (count($imported) > 0) {

            if (count($imported) == 1) {

                $last_imported_index = 0;

            } else {

                $last_imported_index = count($imported) - 1;
            }

            if ($imported[$last_imported_index]['wooproductid'] == $ProductImage['WOO_PRODUCT_ID']){

                if ($imported[$last_imported_index]['epoch'] < $ProductImage['FILE_EPOCH']) {

                    $result['Message'] = "File Newer than last Import";
                    $result['Import'] = TRUE;

                    if ($imported[$last_imported_index]['md5'] == $ProductImage['FILE_MD5']) {

                        $result['Message'] = "File has not changed since last import";
                        $result['Import'] = FALSE;

                    } else {

                        $result['Message'] = "File has changed since last import";
                        $result['Import'] = TRUE;

                    }

                } else {

                    $result['Message'] = "Last Import newer than file";
                    $result['Import'] = FALSE;

                }

            } else {

                $result['Message'] = "Product ID has changed. Treating as new product.";
                $result['Import'] = TRUE;

            }

        } else {

            $result['Message'] = "Not found in import log";
            $result['Import'] = TRUE;

        }

    } else {

        $result['Message'] = "Does not exist";
        $result['Import'] = FALSE;

    }

    return $result;
}

function delete_image($ProductImage){
    
    if ( in_array($ProductImage['FILE_BASE'],$ProductImage['IMPORTLOG']['LOGITEMS'])){
        // shell_exec("rm -rf " . DIR_SITE . "/wp-content/uploads/" . $sku . "*");
    }

}

function getImageGalleryOrder($ProductImage){

    global $mysqli;   
    $galleryorder = array();
    
    $sql_query_attachments = 'SELECT id,post_title
    FROM wp_posts 
    WHERE post_type = "attachment"  and post_parent="' . $ProductImage['WOO_PRODUCT_ID'] . '" ORDER BY post_title';

    $sql_result_attachments = $mysqli->query($sql_query_attachments);
    
    while($sql_data_attachments = $sql_result_attachments->fetch_array(MYSQLI_ASSOC)) {

        $galleryorder[] = [
            "id" => $sql_data_attachments['id'],
            "name" => $sql_data_attachments['post_title']
        ];

    }

    $arr_merge = array();
    foreach ($galleryorder as $order ){
        $arr_merge[] = $order['id'];
    }

    // $ProductImage['GALLERY_ORDER'] = $galleryorder;
    $ProductImage['PRODUCT_GALLERY_REORDERED'] = $arr_merge;
    return $ProductImage;
}

/**
 * ie. [{"id":124244},{"id":122344},{"id":123666},{"id":998333}]
 */
function getImageGallery($ProductImage){

    global $mysqli;   

    // Get Related images in gallery for this product
    $sql_query_gallery = 'SELECT meta_value as gallery FROM wp_postmeta WHERE meta_key="_product_image_gallery" and post_id="' . $ProductImage['WOO_PRODUCT_ID'] . '"';
    
    $sql_result_gallery = $mysqli->query($sql_query_gallery);
    $sql_data_gallery = $sql_result_gallery->fetch_array(MYSQLI_NUM);
    
    if ( strpos($sql_data_gallery[0],",") !== false ){ 
        $woo_prod_img_gallery = explode(",",$sql_data_gallery[0]);
    } else {
        $woo_prod_img_gallery[] = $sql_data_gallery[0];
    }
   
    $woo_prod_img_gallery[] = $ProductImage['ATTACHMENT_ID'];

    foreach ($woo_prod_img_gallery as $item){
        if (!empty($item)){
            $ProductImage['PRODUCT_GALLERY'][] = $item;
        }
    }

    // Reorder the gallery images
    $ProductImage = getImageGalleryOrder($ProductImage);
    
    $jsonitem[0] = '[';

    for ($i=0;$i<count($ProductImage['PRODUCT_GALLERY_REORDERED']);$i++){
        array_push($jsonitem, '{"id":' . $ProductImage['PRODUCT_GALLERY_REORDERED'][$i] . '}');
    }

    array_push($jsonitem, ']');

    $ProductImage['PRODUCT_GALLERY_JSON'] = str_replace("}{","},{",implode($jsonitem));

    return $ProductImage;
}

/**
 * $ProductImage as array
 */
function import($ProductImage) {

    $imgid=null;
    $output=null;
    $retval=null;
    $is_featured="";
    $cli_user = " --user=" . USER_WPCLI;
    $cli_path = " --path=" . DIR_SITE;
    $cli_postid = " --post_id=" . $ProductImage['WOO_PRODUCT_ID'];
    $cli_porcelain = " --porcelain";

    // IS FEATURED
    $fileparts = explode("-",$ProductImage['FILE_BASE']);
    if ($fileparts[1] == 1 || $fileparts[1] == 01){
        $is_featured = " --featured_image";
        $ProductImage['IS_FEATURED'] = TRUE;
    } else {
        $ProductImage['IS_FEATURED'] = FALSE;
    }

    // WP MEDIA IMPORT
    $cli_cmd_import = "sudo -u " . USER_NONROOT . " -i -- wp media import ". $ProductImage['FILE'] . $cli_user . $cli_postid . $is_featured . $cli_porcelain . $cli_path;
            
    // debug($cli_cmd_import);
    exec($cli_cmd_import, $imgid, $retval);
    $ProductImage['ATTACHMENT_ID'] = $imgid[0];
    
    // WP WC PRODUCT UPDATE
    /**
     * BUG: PRODUCT IMAGE GALLERY disregards featured image flag
     * Need to query all attachments from this post/product id 
     * and re-add them
     */
    
    $ProductImage = getImageGallery($ProductImage);
    $cli_images = " --images='" . $ProductImage['PRODUCT_GALLERY_JSON'] . "'";
    $cli_cmd_update = "sudo -u " . USER_NONROOT . " -i -- wp wc product update " . $ProductImage['WOO_PRODUCT_ID'] . $cli_images . $cli_user . $cli_path;

    // debug($cli_cmd_update);
    exec($cli_cmd_update, $output, $retval);
    // debug($output);
    print_r($ProductImage) . PHP_EOL;

    $log_entry = array(TIMESTAMP,$ProductImage['SKU'],$ProductImage['WOO_PRODUCT_ID'],$ProductImage['ATTACHMENT_ID'],$ProductImage['FILE_BASE'],$ProductImage['FILE_MD5']);
    echo "Log Entry: " . implode(",",$log_entry) . PHP_EOL;
    updateLog($log_entry);

    // Stops with each
    // die;
    // - FIN -
    
}


function tar($TARGET,$SOURCE,$exclude="",$deletesrcYN=""){
    
    if ( !empty($exclude)){ $exclude_this = " --exclude='" . $exclude . "'";} else { $exclude_this = "";}
    if ( !empty($deletesrcYN && $deletesrcYN=="Y")){ $with_delete = " --remove-files";} else {$with_delete="";}
    // $cmd = "tar -czvf " . $backup_to . "/" . DATETIME . "-" . $TARGET . ".tgz " . $SOURCE . " ". $exclude . "" . $with_delete;
    $cmd = "tar -czvf " . $TARGET . $exclude_this . " ". $SOURCE . "" . $with_delete;
    return $cmd;

}

/**
 * $type "db", "uploads", "site"
 */
function backup($type){

    $FILE_BACKUP_DB = DATETIME . "-" . DB_NAME . "-db.sql";
    $FILE_BACKUP_SITE = DIR_BACKUP . "/" . DATETIME . "-" . CLIENT_NAME . "-site.tgz";
    $FILE_BACKUP_UPLOADS = DIR_BACKUP . "/" . DATETIME . "-" . CLIENT_NAME . "-site-uploads.tgz";

    $exclude = "*.wpress";
    
    switch ($type){

        case "uploads":

            echo "DB DUMP" . PHP_EOL;
            shell_exec("cd " . DIR_BACKUP . " && " . "sudo mysqldump " . DB_NAME . " > " . $FILE_BACKUP_DB) . PHP_EOL;
            shell_exec("cd " . DIR_BACKUP . " && " . tar($FILE_BACKUP_DB . ".tgz ", $FILE_BACKUP_DB,"","Y")) . PHP_EOL;

            echo "BACKUP UPLOADS" . PHP_EOL;
            shell_exec("cd " . DIR_ROOT . " && " . tar($FILE_BACKUP_UPLOADS,str_replace(DIR_ROOT . "/","",DIR_SITE) . "/wp-content/uploads/",$exclude)) . PHP_EOL;

            break;

        case "site":
    
            echo "DB DUMP" . PHP_EOL;
            shell_exec("cd " . DIR_SITE . " && " . "sudo mysqldump " . DB_NAME . " > " . $FILE_BACKUP_DB) . PHP_EOL;
            shell_exec("cd " . DIR_SITE . " && " . tar($FILE_BACKUP_DB . ".tgz ", $FILE_BACKUP_DB,"","Y")) . PHP_EOL;
        
            echo "BACKUP FULL SITE" . PHP_EOL;
            shell_exec("cd " . DIR_ROOT . " && " . tar($FILE_BACKUP_SITE,str_replace(DIR_ROOT . "/","",DIR_SITE) . "/ ",$exclude)) . PHP_EOL;
            shell_exec("rm -rf " . DIR_SITE . "/" . $FILE_BACKUP_DB . ".tgz") . PHP_EOL;

            break;

    }
}

/**
 * $type "backup", "restore", "list"
 */
function allinone($type,$wpress=""){

    switch ($type){

        case "backup":

            // WARNING: This command waits for Y/N user input. 
            $output1=null;
            $retval=null;
            $cmd = "cd " . DIR_SITE . " && " . "sudo -u " . USER_NONROOT . " wp ai1wm backup --quiet --path=" . DIR_SITE;
            exec($cmd,$output1,$retval) . PHP_EOL;
            print_r($output1);

            $output2=null;
            $retval=null;
            $cmd = "cd " . DIR_SITE . " && " . "sudo -u " . USER_NONROOT . " wp ai1wm list-backups " . " --path=" . DIR_SITE;
            exec($cmd,$output2,$retval) . PHP_EOL;
            echo substr($output2[1], 0, strpos($output2[1], "wpress") + strlen("wpress")) . PHP_EOL;

            break;

        case "restore":

            $output=null;
            $retval=null;
            $cmd = "cd " . DIR_SITE . " && " . "sudo -u " . USER_NONROOT . " wp ai1wm restore " . $wpress . " --path=" . DIR_SITE;
            exec($cmd,$output,$retval) . PHP_EOL;
            print_r($output);

            break;        

        case "list":

            $output=null;
            $retval=null;
            $cmd = "cd " . DIR_SITE . " && " . "sudo -u " . USER_NONROOT . " wp ai1wm list-backups " . " --path=" . DIR_SITE;
            exec($cmd,$output,$retval) . PHP_EOL;
            print_r($output);
            
            break;
    }
}

/**
 * $type "db", "uploads", "site"
 */
function restore($type,$datetime){

    $FILE_BACKUP_DB = $datetime . "-" . DB_NAME . "-db.sql";
    $FILE_BACKUP_SITE = DIR_BACKUP . "/" . $datetime . "-" . CLIENT_NAME . "-site.tgz";
    $FILE_BACKUP_UPLOADS = DIR_BACKUP . "/" . $datetime . "-" . CLIENT_NAME . "-site-uploads.tgz";

    switch ($type){

        case "db":
            
            shell_exec("sudo mysql " . DB_NAME . " < " . $FILE_BACKUP_DB) . PHP_EOL;

            break;

        case "uploads":

            shell_exec("rm -rf " . DIR_ROOT . "/wp-content/uploads/") . PHP_EOL;
            shell_exec("tar -zxvf " . $FILE_BACKUP_UPLOADS . " -C " . DIR_SITE . "/wp-content/uploads/") . PHP_EOL;

            break;

        case "site":

            shell_exec("mv " . DIR_SITE . " " . DIR_SITE . "-" . DATETIME) . PHP_EOL;
            shell_exec("tar -zxvf " . $FILE_BACKUP_SITE . " -C " . DIR_SITE) . PHP_EOL;
            shell_exec("sudo mysql " . DB_NAME . " < " . DIR_SITE . "/" . $FILE_BACKUP_DB) . PHP_EOL;

            break;
    }
    
}

?>