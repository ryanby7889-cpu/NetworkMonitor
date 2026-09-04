<?php

require_once "library/routeros_api.class.php";

echo "Library berhasil dimuat.<br><br>";

if(class_exists('RouterosAPI')){
    echo "Class RouterosAPI ditemukan.";
}else{
    echo "Class RouterosAPI TIDAK ditemukan.";
}