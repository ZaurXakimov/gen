<?php

function restructDate($innerDate) {
    $dateOutArrUno  = date_parse($innerDate);
    $intUnixTimeUno = mktime(
        $dateOutArrUno['hour'],
        $dateOutArrUno['minute'],
        $dateOutArrUno['second'],
        $dateOutArrUno['month'],
        $dateOutArrUno['day'],
        $dateOutArrUno['year']
    );
    return $intUnixTimeUno;
}

// Конвертирует русскую дату из инпута в UNIX формат
// strToDate(6 Января, 2017) => 1483650000
function strToDate($innerStr) {
    $dateArr = explode(" ", $innerStr);
    return restructDate($dateArr[0] . "." . retMonth($innerStr) . "." . $dateArr[2]);
}
