<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class Gurtam
{
    protected $urlapi = 'https://hst-api.wialon.com/wialon/ajax.html?svc';
    protected $tokenapi;
    protected $errors = [
        '0' => 'OK',
        '1' => 'Sesión Inválida',
        '2' => 'Nombre de Servicio inválido',
        '3' => 'Respuesta Inválida',
        '4' => 'Entrada Inválida',
        '5' => 'Error al procesar',
        '6' => 'Error desconocido',
        '7' => 'Acceso denegado',
        '8' => 'Usuario o Clave Inválida',
        '9' => 'Servidor de Autorización no disponible',
        '10' => 'Límite de requests alcanzado',
        '11' => 'Error en reinicio de clave',
        '1001' => 'No hay mensajes para el intervalo seleccionado',
        '1002' => 'Item con alguna propiedad única ya existe o Item no puede ser creado por restricciones de pago',
        '1003' => 'Solo es permitido un request en este momento',
        '1004' => 'Límite de mensajes alcanzado',
        '1005' => 'Tiempo de ejecución excedido',
        '1011' => 'Su IP ha cambiado o su sesión ha expirado',
        '2014' => 'Usuario seleccionado es creador de algunos objetos por lo que este usuario no puede ser movido a una nueva cuenta',
        '2015' => 'Eliminado de sensor prohibido porque es usado por otro sensor o propiedades avanzadas de unidad'
    ];
    
    function __construct($token)
    {
        $this->tokenapi = $token;        
    }

    public function getError($error){
        return $this->errors[$error];
    }

    function loginApi()
    {
        $urlapi = $this->urlapi;
        $apitoken = $this->tokenapi;
        $api = new Client;
        $apiLogin = $api->request('POST', $urlapi.'=token/login', [
            'form_params' => [
                'params' => '{"token":"'.$apitoken.'"}',
            ]
        ]);
        $data = json_decode($apiLogin->getbody(),TRUE);
        return $data;
    }
    function crearcliente(Request $request, $rawpassword){
        $login = self::loginApi();
        $sid = $login['eid'];
        $creatorId1 = $login['user']['id'];
        $urlapi = $this->urlapi;
        $api = new Client;
        $apiRequest = $api->request('POST', $urlapi.'=core/create_user&sid='.$sid, [
            'form_params' => [
                'params' => '{"creatorId":'.$creatorId1.',"name":"'.$request->input("ruc").'","password":"'.$rawpassword.'","dataFlags":5}',
                'sid' => $sid,
            ]
        ]);
        $data = json_decode($apiRequest->getbody(),TRUE);
        $creatorId2 = $data['item']['id'];
        if($request->input('tipocliente')==1){
            $nombre = $request->input("nombres").' '.$request->input("apellidop").' '.$request->input("apellidom");
        }
        if($request->input('tipocliente')==2){
            $nombre = $request->input("nombres");
        }
        $apiResource = $api->request('POST', $urlapi.'=core/create_resource&sid='.$sid, [
            'form_params' => [
                'params' => '{"creatorId":'.$creatorId2.',"name":"'.$nombre.'","skipCreatorCheck":0,"dataFlags":5}',
                'sid' => $sid,
            ]
        ]);
        $data = json_decode($apiResource->getbody(),TRUE);
        $creatorId3 = $data['item']['id'];
        $creatorId4 = $data['item']['crt'];
        $apiResponse = $api->request('POST', $urlapi.'=account/create_account&sid='.$sid, [
            'form_params' => [
                'params' => '{"itemId":'.$creatorId3.',"plan":"telemovil"}',
                'sid' => $sid,
            ]
        ]);
        return $creatorId2.','.$creatorId4;
    }
    function cambiarclave($newpass,$id){
        $login = self::loginApi();
        $sid = $login['eid'];
        $urlapi = $this->urlapi;
        $api = new Client;
        $apiRequest = $api->request('POST', $urlapi.'=core/batch&sid='.$sid, [
            'form_params' => [
                'params' => '{"params":[{"svc":"user/update_password","params":{"userId":'.$id.',"oldPassword":"","newPassword":"'.$newpass.'"}}],"flags":0}',
                'sid' => $sid,
            ]
        ]);
        $data = json_decode($apiRequest->getbody(),TRUE);
    }
    function desactivarcliente($id){
        $login = self::loginApi();
        $sid = $login['eid'];
        $urlapi = $this->urlapi;
        $api = new Client;
        $apiRequest = $api->request('POST', $urlapi.'=account/enable_account&sid='.$sid, [
            'form_params' => [
                'params' => '{"itemId":'.$id.',"enable":0}',
                'sid' => $sid,
            ]
        ]);
        $data = json_decode($apiRequest->getbody(),TRUE);
    }
    function activarcliente($id){
        $login = self::loginApi();
        $sid = $login['eid'];
        $urlapi = $this->urlapi;
        $api = new Client;
        $apiRequest = $api->request('POST', $urlapi.'=account/enable_account&sid='.$sid, [
            'form_params' => [
                'params' => '{"itemId":'.$id.',"enable":1}',
                'sid' => $sid,
            ]
        ]);
        $data = json_decode($apiRequest->getbody(),TRUE);
    }
    function registrarunidad(Request $request){
        $tools = new Tools;
        $customer = $tools->getCustomerData($request->input('cliente'));
        $chipdata = $tools->getChipData($request->input('chip'));
        $devicedata = $tools->getDeviceData($request->input('equipo'));
        $login = self::loginApi();
        $sid = $login['eid'];
        $urlapi = $this->urlapi;
//    dd($this->errors);
        $api = new Client;
        $apiRequest = $api->request('POST', $urlapi.'=core/create_unit&sid='.$sid, [
            'form_params' => [
                'params' => '{"creatorId":"'.($customer->idSeniorcrt).'","name":"'.$request->input('placa').'","hwTypeId":"'.$devicedata->idSeniorModelo.'","dataFlags":"1"}',
                'sid' => $sid,
            ]
        ]);
        $data = json_decode($apiRequest->getbody(),TRUE);
        Log::info($data);
        if(array_key_exists('error',$data)){
            Notify::error(self::getError($data['error']),'Error');
            return redirect()->back();
        }
        else{
            $idDevice = $data['item']['id'];
            $apiRequest2 = $api->request('POST', $urlapi.'=core/batch&sid='.$sid, [
                'form_params' => [
                    'params' => '{
          "params": [{
            "svc": "unit/update_device_type",
            "params": {
              "itemId": '.$idDevice.',
              "deviceTypeId": "'.$devicedata->idSeniorModelo.'",
              "uniqueId": "'.$devicedata->imeiEquipo.'"
            }
          }, {
            "svc": "unit/update_phone",
            "params": {
              "itemId": '.$idDevice.',
              "phoneNumber": "+'.$chipdata->numeroChip.'"
            }
          }],
          "flags": 0
        }',
                    'sid' => $sid,
                ]
            ]);
            $data2 = json_decode($apiRequest2->getbody(),TRUE);
            return $idDevice;
        }

    }
    function eliminarunidad(Request $request,$idunidad){
        $login = self::loginApi();
        $sid = $login['eid'];
        $urlapi = $this->urlapi;
        $api = new Client;
        $apiRequest = $api->request('POST', $urlapi.'=item/delete_item&sid='.$sid, [
            'form_params' => [
                'params' => '{"itemId":"'.$idunidad.'"}',
                'sid' => $sid,
            ]
        ]);
        $data = json_decode($apiRequest->getbody(),TRUE);
        return $data;
    }
    function cambiarchip(Request $request,$idunidad){
        $newChip = $request->input('chip');
        $tools = new Tools;
        $chipdata = $tools->getChipData($request->input('chip'));
        $login = self::loginApi();
        $sid = $login['eid'];
        $urlapi = $this->urlapi;
        $api = new Client;
        $apiRequest = $api->request('POST', $urlapi.'=unit/update_phone&sid='.$sid, [
            'form_params' => [
                'params' => '{"itemId":"'.$idunidad.'","phoneNumber":"+'.$chipdata->numeroChip.'"}',
                'sid' => $sid,
            ]
        ]);
        $data = json_decode($apiRequest->getbody(),TRUE);
        return $data['ph'];
    }
    function cambiarequipo(Request $request, $idunidad){
        $newDevice = $request->input('equipo');
        $tools = new Tools;
        $devicedata = $tools->getDeviceData($request->input('equipo'));
        $login = self::loginApi();
        $sid = $login['eid'];
        $urlapi = $this->urlapi;
        $api = new Client;
        $apiRequest = $api->request('POST', $urlapi.'=core/batch&sid='.$sid, [
            'form_params' => [
                'params' => '{
          "params": [{            
            "svc": "unit/update_device_type",
            "params": {
              "itemId": '.$idunidad.',
              "deviceTypeId": "+'.$devicedata->idSeniorModelo.'",
              "uniqueId": "'.$devicedata->imeiEquipo.'"
            }
          }],
          "flags": 0
        }',
                'sid' => $sid,
            ]
        ]);
        $data = json_decode($apiRequest->getbody(),TRUE);
        return $data;
    }
    function verCuentas(){
        $login = self::loginApi();
        $sid = $login['eid'];
        $urlapi = $this->urlapi;
        $api = new Client;
        $apiRequest = $api->request('POST', $urlapi.'=core/search_items&sid='.$sid, [
            'form_params' => [
                'params' => '{"spec":{	"itemsType":"avl_resource","propName":"sys_name","propValueMask":"*","sortType":"sys_user_creator"}, "force":1,"flags":5,"from":0,"to":0}',
                'sid' => $sid,
            ]
        ]);
        $data = json_decode($apiRequest->getbody(),TRUE);
        return $data;
    }
    function verUnidades(){
        $login = self::loginApi();
        $sid = $login['eid'];
        $urlapi = $this->urlapi;
        $api = new Client;
        $apiRequest = $api->request('POST', $urlapi.'=core/search_items&sid='.$sid, [
            'form_params' => [
                'params' => '{"spec":{	"itemsType":"avl_unit","propName":"sys_name","propValueMask":"*","sortType":"sys_user_creator"}, "force":1,"flags":5,"from":0,"to":0}',
                'sid' => $sid,
            ]
        ]);
        $data = json_decode($apiRequest->getbody(),TRUE);
        return $data;
    }

    function exportarReporteMonitoreo(){
        $login = self::loginApi();
        $sid = $login['eid'];
        $urlapi = $this->urlapi;
        $api = new Client;
        $apiRequest = $api->request('POST', $urlapi.'=core/export_file&sid='.$sid, [
            'form_params' => [
                'params' => '{"spec":{"itemsType":"avl_unit","propName":"*","propValueMask":"*","sortType":"sys_name"},"force":"0","flags":"125"}',
                'sid' => $sid,
            ]
        ]);
//        dd($apiRequest);
        $nombrereporte = 'Reporte_'.Carbon::now()->toTimeString().'.xlsx';
        Storage::put($nombrereporte,$apiRequest->getbody());

        return $nombrereporte;
    }

    function getLastPosition($id){
        $login = self::loginApi();
        $sid = $login['eid'];
        $urlapi = $this->urlapi;
        $api = new Client;
        $apiRequest = $api->request('POST', $urlapi.'=core/search_item&sid='.$sid, [
            'form_params' => [
                'params' => '{"id":"'.$id.'","flags":"4194304"}',
                'sid' => $sid,
            ]
        ]);
        $data = json_decode($apiRequest->getbody(),TRUE);
        $data = array(
            'latitud' => $data['item']['pos']['y'],
            'longitud' => $data['item']['pos']['x'],
            'orientacion' => $data['item']['pos']['c'],
            'velocidad' => $data['item']['pos']['s'],
            'fecha' => $data['item']['pos']['t']
        );
        return $data;
    }

    function getLastPosition2($id){
        $login = self::loginApi();
        if(!array_key_exists('eid',$login)){
            $data = array('error' => '1','mensaje' => $login);
            return json_encode($data);
        }
        $sid = $login['eid'];
        $urlapi = $this->urlapi;
        $api = new Client;
        try{
            $apiRequest = $api->request('POST', $urlapi.'=core/search_item&sid='.$sid, [
                'form_params' => [
                    'params' => '{"id":"'.$id.'","flags":"4194304"}',
                    'sid' => $sid,
                ]
            ]);
            $data = json_decode($apiRequest->getbody(),TRUE);
            if(array_key_exists('error',$data)){
                $data = array('error' => '1','mensaje' => $data);
                return json_encode($data);
            }
            $paquete = array(
                'latitud' => $data['item']['pos']['y'],
                'longitud' => $data['item']['pos']['x'],
                'orientacion' => $data['item']['pos']['c'],
                'velocidad' => $data['item']['pos']['s'],
                'fecha' => $data['item']['pos']['t']
            );
            $data = array('error'=>'0','mensaje'=>$paquete);
            return json_encode($data);
        }
        catch(ClientException $e){
            $data = array('error' => '1','mensaje' => $e->getResponse()->getBody()->getContents());
            return json_encode($data);
        }

    }

    function getAccountData($type){
        $login = self::loginApi();
        if(!array_key_exists('eid',$login)){
            $data = array('error' => '1','mensaje' => $login);
            return json_encode($data);
        }
        $sid = $login['eid'];
        $urlapi = $this->urlapi;
        $api = new Client;
        try{
            $apiRequest = $api->request('POST', $urlapi.'=core/get_account_data&sid='.$sid, [
                'form_params' => [
                    'params' => '{"type":"'.$type.'"}',
                    'sid' => $sid,
                ]
            ]);
            $data = json_decode($apiRequest->getbody(),TRUE);
//            dd($data);
            if(array_key_exists('error',$data)){
                $data = array('error' => '1','mensaje' => $data);
                return json_encode($data);
            }
            else{
                $paquete = array(
                    'unidadesTotales' => $data['services']['avl_unit']['maxUsage'],
                    'unidadesUsadas' => $data['services']['avl_unit']['usage'],
                    'diasRestantes' => $data['daysCounter']
                );
                $data = array('error'=>'0','mensaje'=>$paquete);
                return json_encode($data);
            }
        }
        catch(ClientException $e){
            $data = array('error' => '1','mensaje' => $e->getResponse()->getBody()->getContents());
            return json_encode($data);
        }
    }
}