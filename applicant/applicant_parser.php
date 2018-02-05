<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/gen/connect.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/gen/config.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/gen/public_function/utils.php';

class FieldsParser{
    public $id_packet;
    public $connect;

    function __construct($id_packet = NULL) {
        global $connect;
        $this->connect = $connect;
        $this->id_packet = $id_packet;
    }

    public function setIdPacket($id_packet) {
        $this->id_packet = $id_packet;
    }

    public function getPacketParams($id_packet = NULL){
        $connect = $this->connect;
        if (!$id_packet) {
            $id_packet = $this->id_packet;
        }

        $sql_p = "SELECT * FROM dbo.Virtual_packets WHERE id_packet = ?";
        $result_g = odbc_prepare($connect, $sql_p);
        odbc_execute($result_g, array($id_packet));
        $packet_param_array = null;
        $p = odbc_fetch_array($result_g);
        foreach ($p as $key => $value) {
            $p[$key] = iconv("CP1251", "UTF-8", $value);
        }

        $packet_param_array = [
            'id_packet'        => [
                'dbname' => 'id_packet',
                'value'  => $p['id_packet'],
                'label'  => 'Идентификатор пакета'],
            'status'        => [
                'dbname' => 'status',
                'value'  => $p['status'],
                'label'  => 'Статус пакета'],
            'date_beg'        => [
                'dbname' => 'date_beg',
                'value'  => $p['date_beg'],
                'label'  => 'Датавремя начала обработки пакета'],
            'date_end'        => [
                'dbname' => 'date_end',
                'value'  => $p['date_end'],
                'label'  => 'Прогнозируемая дата завершения'
            ],
            'id_otdel_out'        => [
                'dbname' => 'id_otdel_out',
                'value'  => $p['id_otdel_out'],
                'label'  => 'Филиал, где выдали пакет документов'],
            'id_otdel_to_out'        => [
                'dbname' => 'id_otdel_to_out',
                'value'  => $p['id_otdel_to_out'],
                'label'  => 'Филиал, где надо выдать пакет документов'],
            'id_otdel_in'        => [
                'dbname' => 'id_otdel_in',
                'value'  => $p['id_otdel_in'],
                'label'  => 'Филиал, где сформирован пакет документов'],
            'id_otdel_main'        => [
                'dbname' => 'id_otdel_main',
                'value'  => $p['id_otdel_main'],
                'label'  => ''],
            'id_th'        => [
                'dbname' => 'id_th',
                'value'  => $p['id_th'],
                'label'  => 'Идентификатор темы'],
            'matr_th'    => [
                'dbname' => 'matr_th',
                'value'  => $p['matr_th'],
                'label'  => 'Конечный узел матрицы'
            ]
        ];
        return $packet_param_array;
    }

    public function getFieldsApplicant($id_client){
        $connect = $this->connect;
        $id_packet = $this->id_packet;

        $sql_g = "SELECT * 
                    FROM dbo.Virtual_packets_group 
                   WHERE id_packet = '$id_packet'
                     AND id_client_un = $id_client";
        $result_g = odbc_prepare($connect, $sql_g);
        odbc_execute($result_g);

        $applicant_fields_array = null;
        while ($g = odbc_fetch_array($result_g)) {
            foreach ($g as $key => $value) {
                $g[$key] = iconv("CP1251", "UTF-8", $value);
            }
            if ($g['type_client'] == 'физлицо') {
                // Физ-лицо
                $applicant_fields_array['f_client'] = [
                    'dbname' => 'f_client',
                    'value' => $g['f_client'],
                    'label' => 'Фамилия'];
                $applicant_fields_array['n_client'] = [
                    'dbname' => 'n_client',
                    'value' => $g['n_client'],
                    'label' => 'Имя'];
                $applicant_fields_array['frt_client'] = [
                    'dbname' => 'frt_client',
                    'value' => $g['frt_client'],
                    'label' => 'Очество'];
                $applicant_fields_array['snils'] = [
                    'dbname' => 'snils',
                    'value' => $g['snils'],
                    'label' => 'СНИЛС'];
                $applicant_fields_array['pasp_ser'] = [
                    'dbname' => 'pasp_ser',
                    'value' => $g['pasp_ser'],
                    'label' => 'Серия и номер документа'];
                $applicant_fields_array['cl_pasp_is'] = [
                    'dbname' => 'cl_pasp_is',
                    'value' => $g['cl_pasp_is'],
                    'label' => 'Код подразделенияа'];
                $applicant_fields_array['cl_pasp_date'] = [
                    'dbname' => 'cl_pasp_date',
                    'value' => $g['cl_pasp_date'],
                    'label' => 'Действителен до'];
                $applicant_fields_array['cl_pasp_issue'] = [
                    'dbname' => 'cl_pasp_issue',
                    'value' => $g['cl_pasp_issue'],
                    'label' => 'Дата выдачи'];
                $applicant_fields_array['cl_date_birth'] = [
                    'dbname' => 'cl_date_birth',
                    'value' => $g['cl_date_birth'],
                    'label' => 'Дата рождения'];
                $applicant_fields_array['INN'] = [
                    'dbname' => 'INN',
                    'value' => $g['INN'],
                    'label' => 'ИНН'];
                $applicant_fields_array['cl_pasp_issue_is'] = [
                    'dbname' => 'cl_pasp_issue_is',
                    'value' => $g['cl_pasp_issue_is'],
                    'label' => 'Кем выдан'];
                $applicant_fields_array['cl_fias_addr'] = [
                    'dbname' => 'cl_fias_addr',
                    'value' => $g['cl_fias_addr'],
                    'label' => 'Адрес'];
            } else {
                // Юр-лицо и ИП

                $applicant_fields_array['name_org'] = [
                    'dbname' => 'name_org',
                    'value' => $g['name_org'],
                    'label' => 'Название организации'];
                $applicant_fields_array['INN'] = [
                    'dbname' => 'INN',
                    'value' => $g['INN'],
                    'label' => 'ИНН'];
                $applicant_fields_array['ogrn'] = [
                    'dbname' => 'ogrn',
                    'value' => $g['ogrn'],
                    'label' => 'ОГРН'];
                $applicant_fields_array['F_predst'] = [
                    'dbname' => 'F_predst',
                    'value' => $g['F_predst'],
                    'label' => 'Фамилия'];
                $applicant_fields_array['N_predst'] = [
                    'dbname' => 'N_predst',
                    'value' => $g['N_predst'],
                    'label' => 'Имя'];
                $applicant_fields_array['Frt_predst'] = [
                    'dbname' => 'Frt_predst',
                    'value' => $g['Frt_predst'],
                    'label' => 'Очество'];
                $applicant_fields_array['pasp_ser_dov'] = [
                    'dbname' => 'pasp_ser_dov',
                    'value' => $g['pasp_ser_dov'],
                    'label' => 'Серия и номер документа'];
                $applicant_fields_array['pr_pasp_date'] = [
                    'dbname' => 'pr_pasp_date',
                    'value' => $g['pr_pasp_date'],
                    'label' => 'Действителен до'];
                $applicant_fields_array['pr_pasp_issue'] = [
                    'dbname' => 'pr_pasp_issue',
                    'value' => $g['pr_pasp_issue'],
                    'label' => 'Дата выдачи'];
                $applicant_fields_array['pr_pasp_is'] = [
                    'dbname' => 'pr_pasp_is',
                    'value' => $g['pr_pasp_is'],
                    'label' => 'Кем выдан'];
            }

            $applicant_fields_array['type_client'] = [
                'dbname' => 'type_client',
                'value' => $g['type_client'],
                'label' => 'Тип клиента'];
            $applicant_fields_array['predst'] = [
                'dbname' => 'predst',
                'value' => $g['predst'],
                'label' => 'Есть представитель'];

            if ($g['predst']){
                // Предстовитель

                $applicant_fields_array['F_predst'] = [
                    'dbname' => 'F_predst',
                    'value' => $g['F_predst'],
                    'label' => 'Фамилия'];
                $applicant_fields_array['N_predst'] = [
                    'dbname' => 'N_predst',
                    'value' => $g['N_predst'],
                    'label' => 'Имя'];
                $applicant_fields_array['Frt_predst'] = [
                    'dbname' => 'Frt_predst',
                    'value' => $g['Frt_predst'],
                    'label' => 'Очество'];
                $applicant_fields_array['pasp_ser_dov'] = [
                    'dbname' => 'pasp_ser_dov',
                    'value' => $g['pasp_ser_dov'],
                    'label' => 'Серия и номер паспорта'];
                $applicant_fields_array['pr_pasp_date'] = [
                    'dbname' => 'pr_pasp_date',
                    'value' => $g['pr_pasp_date'],
                    'label' => 'Действителен до'];
                $applicant_fields_array['pr_pasp_issue'] = [
                    'dbname' => 'pr_pasp_issue',
                    'value' => $g['pr_pasp_issue'],
                    'label' => 'Дата выдачи'];
                $applicant_fields_array['pr_pasp_is'] = [
                    'dbname' => 'pr_pasp_is',
                    'value' => $g['pr_pasp_is'],
                    'label' => 'Кем выдан'];
                $applicant_fields_array['dov_notarius'] = [
                    'dbname' => 'dov_notarius',
                    'value' => $g['dov_notarius'],
                    'label' => 'Нотариус'];
                $applicant_fields_array['dov_numb'] = [
                    'dbname' => 'dov_numb',
                    'value' => $g['dov_numb'],
                    'label' => 'Номер доверенности'];
                $applicant_fields_array['dov_notarius'] = [
                    'dbname' => 'dov_notarius',
                    'value' => $g['dov_notarius'],
                    'label' => 'Действительна до'];
            }

            $applicant_fields_array['id_ident_doc'] = [
                'dbname' => 'id_ident_doc',
                'value' => $g['id_ident_doc'],
                'label' => 'Документ удостоверяющий личность'];
            $applicant_fields_array['cl_addr'] = [
                'dbname' => 'cl_addr',
                'value' => $g['cl_addr'],
                'label' => 'Адресс заявителя'];
            $applicant_fields_array['cl_addr_KLADR'] = [
                'dbname' => 'cl_addr_KLADR',
                'value' => $g['cl_addr_KLADR'],
                'label' => 'Адресс заявителя'];
            $applicant_fields_array['cl_phone'] = [
                'dbname' => 'cl_phone',
                'value' => $g['cl_phone'],
                'label' => 'Номер телефона'];
            $applicant_fields_array['cl_email'] = [
                'dbname' => 'cl_email',
                'value' => $g['cl_email'],
                'label' => 'Электронная почта'];
            $applicant_fields_array['cl_notes'] = [
                'dbname' => 'cl_notes',
                'value' => $g['cl_notes'],
                'label' => 'Заметки'];
        }

        return $applicant_fields_array;
    }

    private function gettingFieldsAdd($sql){
        $connect   = $this->connect;

        $res_af = odbc_exec($connect, $sql);

        $add_fields_array= null;
        while ($add_fields = odbc_fetch_array($res_af)) {
            foreach ($add_fields as $key => $value) {
                $add_fields[$key] = iconv("CP1251", "UTF-8", $value);
            }
            // Cелект
            $descr = '';
            if ($add_fields['field_type'] == 6) {
                $sql_fs = "SELECT sl.scheme, sl.name 
                           FROM dbo.Virtual_fields_source fs
                           JOIN dbo.Virtual_sources_list sl ON sl.id_source = fs.id_source
                           WHERE fs.id_field = '$add_fields[uid]'";
                $res_fs = odbc_exec($connect, $sql_fs);
                $scheme = odbc_result($res_fs, 'scheme');
                $table  = odbc_result($res_fs, 'name');
                $sql_s  = "SELECT [name] FROM $scheme.$table WHERE [code] = '$add_fields[value_field]'";
                $res_s  = odbc_exec($connect, $sql_s);
                $descr  = get_db_string($res_s, 'name');
            }
            // Мультиселект
            if ($add_fields['field_type'] == 14) {
                $sql_ms = "SELECT [value] FROM dbo.packets_group_multiselect WHERE GUID = '$add_fields[value_field]'";
                $res_ms = odbc_exec($connect, $sql_ms);
                $ms_arr = [];
                while ($ms = odbc_fetch_array($res_ms)){
                    $ms_arr[] = $ms['value'];
                }
                $add_fields['value_field'] = empty($ms_arr) ? '' : $ms_arr;
            }
            $add_fields['entity_inc'] = isset($add_fields['entity_inc']) ? $add_fields['entity_inc'] : '';
            $add_fields_array[$add_fields['uid']] = [
                'uid'          => $add_fields['uid'],
                'dbname'       => $add_fields['name'],
                'value'        => $add_fields['value_field'],
                'display_field'=> $add_fields['display_field'],
                'descr'        => $descr,
                'label'        => $add_fields['decsr'],
                'field_type'   => $add_fields['field_type'],
                'entity_id'    => $add_fields['id_entity'],
                'entity_inc'   => $add_fields['entity_inc'],
                'entity_name'  => $add_fields['description']
            ];
        }
        return $add_fields_array;
    }

    public function getFieldsAdd($id_client){
        $connect   = $this->connect;
        $id_packet = $this->id_packet;

        $sql_add_fields = "SELECT 
                                   f.uid
                                  ,f.name
                                  ,f.decsr
                                  ,f.field_type
                                  ,pga.value_field
                                  ,pga.display_field
                                  ,fe.id_entity
                                  ,fe.description
                             FROM dbo.Virtual_packets_group_add pga
                        LEFT JOIN dbo.Virtual_fields f ON f.uid = pga.id_field
                        LEFT JOIN dbo.Virtual_fields_entity fe ON f.field_entity = fe.id_entity
                            WHERE id_packet = '$id_packet'
                              AND id_client_un = $id_client
							  UNION
							  SELECT 
                                   f.uid
                                  ,f.name
                                  ,f.decsr
                                  ,f.field_type
                                  ,pga.value_field
                                  ,pga.display_field
                                  ,fe.id_entity
                                  ,fe.description
                             FROM dbo.Virtual_packets_group_add pga
                        LEFT JOIN dbo.Virtual_fields f ON f.uid = pga.id_field
                        LEFT JOIN dbo.Virtual_fields_entity fe ON f.field_entity = fe.id_entity
                            WHERE id_packet = '$id_packet'";
        return $this->gettingFieldsAdd($sql_add_fields);
    }

    public function getFieldsFree($entity = null, $entity_inc = null){
        $connect   = $this->connect;
        $id_packet = $this->id_packet;

        if ($entity){
            $sql_ent = "AND entity_inc = $entity";
        } else {
            $sql_ent = "AND entity_inc IS NOT NULL";
        }

        if ($entity_inc){
            $sql_ent_inc = "AND entity_inc = $entity_inc";
        } else {
            $sql_ent_inc = "AND entity_inc IS NOT NULL";
        }

        $free_fields_array = null;
        $sql_add_fields = "SELECT 
                                   f.uid
                                  ,f.name
                                  ,f.decsr
                                  ,f.field_type
                                  ,pga.value_field 
                                  ,fe.id_entity
                                  ,fe.description
                                  ,pga.entity_inc
                             FROM dbo.Virtual_packets_group_add pga
                        LEFT JOIN dbo.Virtual_fields f ON f.uid = pga.id_field
                        LEFT JOIN dbo.Virtual_fields_entity fe ON f.field_entity = fe.id_entity
                            WHERE id_packet = '$id_packet'
                                  $sql_ent_inc";
        return $this->gettingFieldsAdd($sql_add_fields);
    }

    public function getFieldsAll()
    {
        $connect = $this->connect;
        $id_packet = $this->id_packet;

        if ($id_packet) {

            // Запрос основных параметров пакета
            $sql_g = "SELECT id_client_un 
                    FROM dbo.Virtual_packets_group 
                   WHERE id_packet = '$id_packet'";
            $result_g = odbc_prepare($connect, $sql_g);
            odbc_execute($result_g);
            $applicant = null;
            while ($g = odbc_fetch_array($result_g)) {
                $applicant[$g['id_client_un']] = ['AppFields' => $this->getFieldsApplicant($g['id_client_un']),
                    'AddFields' => $this->getFieldsAdd($g['id_client_un'])];
            }
            $fieldsFree = $this->getFieldsFree();
            if ($fieldsFree){
                $fields = [
                    'Applicant'  => $applicant,
                    'FreeFields' => $fieldsFree
                ];
            } else {
                $fields = [
                    'Applicant'  => $applicant
                ];
            }

            return $fields;
        } else {
            return 'Пакет не задан!';
        }
    }

    public function getDocuments($base64 = false, $uidDoc = NULL){
        $connect = $this->connect;
        $id_packet = $this->id_packet;

        if ($uidDoc) $whereAddUidDoc = " AND inter_id = $uidDoc ";
        else         $whereAddUidDoc = "";

        // Прикрепление документов к пакету
        $sql_d = "SELECT * FROM dbo.Virtual_oper_doc WHERE id_packet = '$id_packet' AND printed = 1 AND docs_img IS NOT NULL $whereAddUidDoc";
        $result_d = odbc_prepare($connect, $sql_d);
        odbc_execute($result_d);

        $sqlDocImgPath = "SELECT TOP(1) base_puth_scan_for_visible, base_puth_scan_for_read FROM Virtual_gen_inf";
        $resultDocImgPath = odbc_prepare($connect, $sqlDocImgPath);
        odbc_execute($resultDocImgPath);

        $parentPath = iconv("cp1251", "utf-8", odbc_result($resultDocImgPath, 'base_puth_scan_for_read'));
        $Document = [];
        while ($d = odbc_fetch_array($result_d)) {
            $d['doc']      = iconv("CP1251", "UTF-8", $d['doc']);
            $d['type_doc'] = iconv("CP1251", "UTF-8", $d['type_doc']);
            $d['docs_img'] = iconv("CP1251", "UTF-8", $d['docs_img']);

            $src['sql']    = $parentPath . '\\' . $d['id_packet'] . '\\' . $d['inter_id'] . '\\' . $d['docs_img'];
            $src['server'] = $_SERVER['DOCUMENT_ROOT'] . '\\gen\\img_docs\\' . $d['id_packet'] . '\\' . $d['inter_id'] . '\\' . $d['docs_img'];
            $src['root']   = 'img_docs/' . $d['id_packet'] . '/' . $d['inter_id'] . '/' . $d['docs_img'];

            $img = iconv("utf-8", "cp1251", $src['sql']);
            $path_parts = pathinfo($img);
            if ($base64){
                $fPack = fopen($img, "rb");
                $code_file = base64_encode(fread($fPack, filesize($img)));
                fclose($fPack);
            } else $code_file = '';

            $Document[] = [
                'DocumentId'        => $d['inter_id'],
                'VocID'             => $d['voc_id_doc'],
                'Type'              => $d['doc'],
                'Extension'         => $path_parts['extension'],
                'Data'              => $code_file,
                'FilePath'          => $img,
                'FileName'          => $d['docs_img'],
                'DocumentType'      => $d['type_doc']];
        }
        return $Document;
    }
}

class Export{
    public $id_packet;
    public $uidDoc;
    public $get;

    function __construct($id_packet = NULL) {
        if (!$id_packet) {
            $id_packet = $this->id_packet;
        }
        $this->get = new FieldsParser($id_packet);
    }

    public function setIdPacket($id_packet) {
        $this->id_packet = $id_packet;
    }

    public function getFieldsArray($id_packet = NULL){
        if ($id_packet) {
            $this->setIdPacket($id_packet);
        }
        return $this->get->getFieldsAll();
    }

    public function getDocumentsArray($id_packet = NULL){
        if ($id_packet) {
            $this->setIdPacket($id_packet);
        }
        return $this->get->getDocuments();
    }

    public function getDocumentsArrayBase64($id_packet = NULL){
        if ($id_packet) {
            $this->setIdPacket($id_packet);
        }
        return $this->get->getDocuments(true);
    }

    public function getAddedDocument_func($uidDoc){
        return $this->get->getDocuments(false, $uidDoc);
    }

    public function getPacketParamsArray($id_packet = NULL){
        if ($id_packet) {
            $this->setIdPacket($id_packet);
        }
        return $this->get->getPacketParams();
    }
}