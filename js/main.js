function sendPacketCommentSir(id_packet, comment){
    var commentSIR = document.getElementById('commentTextArea_'+id_packet);
    console.log(commentSIR);
    $.ajax({
        type: "POST",
        url: "../add_comment.php",
        data: {
            id_packet: id_packet,
            comment:   comment
        },
        success: function(data){
            $(data).insertBefore('#commentTable_'+id_packet+' > tbody > tr:first');
            commentSIR.value = '';
        }
    })
}

function getPressedKeyCommWnd(e,id_packet) {
    e = e || event;
    if (e.keyCode === 13) {
        var commentSIR = document.getElementById('commentTextArea_'+id_packet).value;
        sendPacketCommentSir(id_packet, commentSIR);
    }
    return true;
}

function cbSendItems(group, method) {
    var arr = [];
    $('input[data-cb-item='+group+']:checked').each(function (i, m) {
        arr.push(m.value);
    });

    if (arr.length > 0){
        if (method === 1) sendSIRarr(arr);
    }
}

function clickInfo(regnumb) {
    document.getElementById('modal-user-info').innerHTML = "<h3>Выполнение. Пожалуйста, ждите...</h3>";
    var InfoReestr = "InfoReestr=" + regnumb;
    var reqThOrg = getXmlHttp();

    reqThOrg.onreadystatechange = function() {
        if (reqThOrg.readyState == 4) {
            if (reqThOrg.status == 200) {
                document.getElementById('modal-user-info').innerHTML = reqThOrg.responseText;
            } else {
                var errorCode = reqThOrg.status;
                document.getElementById('modal-user-info').innerHTML = "<h3>Ошибка сети [" + errorCode + "]<br>Пожалуйста повторите запрос.</h3><br>";
            }
        }
    }
    reqThOrg.open('POST', 'info_r.php', true);
    reqThOrg.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    reqThOrg.send(InfoReestr);
    return app.showModal('modal-user-info');
}

function sendSIR(id_packet) {
    $.ajax({
        type: "POST",
        url: "/gen/Package_sender.php",
        data: "id_packet=" + id_packet,
        success: function(msg){
            if (msg) $('#dynBut_'+id_packet).html('<button class="button button-simple" style=" color: green; ">ГОТОВО</button>');
            else $('#dynBut_'+id_packet).html('<button class="button button-simple" style=" color: red; ">ОШИБКА</button>');
        },
        error: function (xhr, ajaxOptions, thrownError) {
            $('#dynBut_'+id_packet).html('<button class="button button-simple" style=" color: red; ">ОШИБКА</button>');
        }
    });
    $('#dynBut_'+id_packet).html('<button class="button button-simple button-ghost" disabled><div class="loader" style="margin: 14px 21px;"></button>');
}

function sendSIRarr(arr) {
    if (arr.length > 0){
        var timeout = 0;

        for (var i=0; i < arr.length; i++){
            setTimeout(
                (function (N){
                    return function(){sendSIR(arr[N])};
                })(i)
                ,
                timeout

            );
            timeout += 3000;
        }
    }
}

function cbItem(e) {
    var group   = e.attr('data-cb-item');
    var cbVal   = Boolean(e.attr('checked'));
    var check   = $('span[data-cb-checked-items='+group+']');
    var cbGroup = $('input[data-cb-group='+group+']');
    var count   = cbGroup.attr('data-cb-count');
    var checked = check.html();

    if (!count) {
        count = $('input[data-cb-item='+group+']').length;
        $('span[data-cb-count-items='+group+']').html(count);
        cbGroup.attr('data-cb-count', count)
    }

    cbUnchecked(group);

    if (cbVal) checked++;
    else checked--;

    check.html(checked);

    if(checked <= 0) {
        cbGroup.prop("indeterminate", false);
        cbGroup.attr('checked', false);
    }

    if(checked == count) {
        cbGroup.prop("indeterminate", false);
        cbGroup.attr('checked', true);
    }

    if(checked > 0 && checked < count) {
        cbGroup.prop("indeterminate", true);
    }
}

function cbGroup(e) {
    var group = e.attr('data-cb-group');
    var mainCbVal = Boolean(e.attr('checked'));
    var cb = 0;

    cbUnchecked(group);

    $('input[data-cb-item='+group+']').each(function() {
        $(this).attr('checked', mainCbVal);
        cb++;
    });
    $('span[data-cb-count-items='+group+']').html(cb);
    e.attr('data-cb-count', cb);
    if (!mainCbVal) cb = 0;
    $('span[data-cb-checked-items='+group+']').html(cb);
}

function parseTable(selector, cols) {
    if (!cols) cols = [];
    var table = 'table#'+selector+' tr';
    var tbl = $(table).get().map(function(row) {
        if ($(row).find('th').length){
            return $(row).find('th').get().map(function(cell) {
                return $(cell).html();
            });
        } else {
            return $(row).find('td').get().map(function(cell) {
                return $(cell).html().replace(/<\/?[^>]+>/g,'').replace(/\s/g, ' ').replace(/\s{2,}/g, ' ').replace(/(^\s*)|(\s*)$/g, '');
            });
        }
    });

    if (cols.length) {
        cols = cols.sort().reverse();
        $(tbl).each(function (k, row) {
            for (var i = 0; i < cols.length; i++) {
                tbl[k].splice(cols[i], 1);
            }
        })
    }

    localStorage.setItem(selector, JSON.stringify(tbl));
    window.open('print_ls.html?selector='+selector, '_blank');
}

function addSkanState(id_packet, uidDoc, fileName){
    $.ajax({
        type: "POST",
        url: "InsertAddedSkans.php",
        data: {
            id_packet: id_packet,
            uidDoc   : uidDoc,
            fileName : fileName
        },
        success: function (data) {
            console.log('Всё ок!');
        }
    })
}

function getXmlHttp()
{
    var xmlhttp;
    try
    {
        xmlhttp = new ActiveXObject("Msxml2.XMLHTTP");
    }
    catch (e)
    {
        try
        {
            xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
        }
        catch (E)
        {
            xmlhttp = false;
        }
    }

    if (!xmlhttp && typeof XMLHttpRequest!='undefined')
    {
        xmlhttp = new XMLHttpRequest();
    }
    return xmlhttp;
}