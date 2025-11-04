<?php
class HYDROP extends IPSModule
{
    public function Create()
    {
        parent::Create();
        // Konfigurations-Properties
        $this->RegisterPropertyString('BaseUrl', 'https://api.hydrop-systems.com');
        $this->RegisterPropertyString('AuthHeaderName', 'apikey');
        $this->RegisterPropertyString('AuthHeaderPrefix', '');
        $this->RegisterPropertyString('ApiKey', '');
        $this->RegisterPropertyString('EndpointPath', '/sensors/all/newest');
        $this->RegisterPropertyString('MeterId', '');
        $this->RegisterPropertyBoolean('EnableAutoMap', false);
        $this->RegisterPropertyInteger('PollSeconds', 300);

        // Timer → ruft per RequestAction die Poll-Methode auf
        $this->RegisterTimer('PollTimer', 0, 'IPS_RequestAction($_IPS["TARGET"], "Poll", 0);');

        // Profil einmal sicherstellen, bevor Variablen angelegt werden
        $this->CreateProfiles();

        // Kern-Variablen
        @$this->RegisterVariableFloat('Total', $this->Translate('Total Consumption'), 'HYDROP.WaterVolume');
        @$this->RegisterVariableFloat('FlowLMin', $this->Translate('Flow Rate'), 'HYDROP.FlowRate', 1);
        @$this->RegisterVariableInteger('LastTimestamp', $this->Translate('Time Stamp'), '~UnixTimestamp');
        @$this->RegisterVariableString('DeviceID', $this->Translate('Device'), '');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Profil bei Änderungen/Neu-Laden sicherstellen
        $this->CreateProfiles();
        
        // Falls es die Variable schon gab (z. B. früher mit ~Water), Profil umstellen
        $vidTotal = @$this->GetIDForIdent('Total');
        if ($vidTotal) {
        IPS_SetVariableCustomProfile($vidTotal, 'HYDROP.WaterVolume');
        }

        $vidFlow = @$this->GetIDForIdent('FlowLMin');
        if ($vidFlow) {
        IPS_SetVariableCustomProfile($vidFlow, 'HYDROP.FlowRate');
        }
        
        $seconds = (int)$this->ReadPropertyInteger('PollSeconds');
        if ($seconds < 10) $seconds = 10;
        $this->SetTimerInterval('PollTimer', $seconds * 1000);
        $this->SetStatus(104); // Aktiv
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Poll':
                $this->Poll();
                return true;
            case 'TestOnce':
                $this->Poll(true);
                return true;
            default:
                throw new Exception('Unknown action ' . $Ident);
        }
    }

    private function CreateProfiles()
    {
    // --- Profil für Gesamtverbrauch (m³) ---
    $p1 = 'HYDROP.WaterVolume';
    if (!IPS_VariableProfileExists($p1)) {
        IPS_CreateVariableProfile($p1, VARIABLETYPE_FLOAT);
        IPS_SetVariableProfileText($p1, '', ' m³');
        IPS_SetVariableProfileDigits($p1, 3);
        IPS_SetVariableProfileIcon($p1, 'Drops');
    }

    // --- Profil für Durchfluss (L/min) ---
    $p2 = 'HYDROP.FlowRate';
    if (!IPS_VariableProfileExists($p2)) {
        IPS_CreateVariableProfile($p2, VARIABLETYPE_FLOAT);
        IPS_SetVariableProfileText($p2, '', ' L/min');
        IPS_SetVariableProfileDigits($p2, 1);
        IPS_SetVariableProfileIcon($p2, 'Gauge');
        }
    }

    private function Poll($manual = false)
    {
        $url = $this->buildEndpoint();
        if ($url === '') {
            $this->SendDebug('HYDROP', 'Endpoint not configured', 0);
            $this->SetStatus(201);
            return;
        }

        try {
            $json = $this->apiGET($url);
            $this->SendDebug('HYDROP Response', substr($json, 0, 4000), 0);
            $data = @json_decode($json, true);
            if (!is_array($data)) {
                throw new Exception('Invalid JSON');
            }

            // Bekannte Felder: sensors[0].records[0]
            $record = $this->extractNewestRecord($data);
            if ($record === null) {
                $this->SendDebug('HYDROP', 'Kein Record gefunden', 0);
                $this->SetStatus(104);
                return;
            }

            $deviceId = isset($data['sensors'][0]['deviceID']) ? (string)$data['sensors'][0]['deviceID'] : '';
            if ($deviceId !== '') {
                $this->MaintainVariable('DeviceID', 'Gerät', VARIABLETYPE_STRING, '', 0, true);
                SetValueString($this->GetIDForIdent('DeviceID'), $deviceId);
            }
            if (isset($record['meterValue'])) {
                $this->MaintainVariable('Total', 'Gesamtverbrauch', VARIABLETYPE_FLOAT, 'HYDROP.Water', 0, true);
                SetValueFloat($this->GetIDForIdent('Total'), floatval($record['meterValue']));
            }
            if (isset($record['timestamp'])) {
                $this->MaintainVariable('LastTimestamp', 'Zeitstempel', VARIABLETYPE_INTEGER, '~UnixTimestamp', 0, true);
                SetValueInteger($this->GetIDForIdent('LastTimestamp'), intval($record['timestamp']));
            }

            // Durchfluss aus Delta berechnen (L/min)
            $this->updateFlowFromDelta($deviceId, $record);

            // Optional: Auto-Mapper nur wenn explizit gewünscht
            if ($this->ReadPropertyBoolean('EnableAutoMap')) {
                $this->autoMapJson($data);
            }

            $this->SetStatus(104);
        } catch (Exception $e) {
            $this->SendDebug('HYDROP Error', $e->getMessage(), 0);
            $this->SetStatus(202);
        }
    }

    private function extractNewestRecord($data)
    {
        if (!isset($data['sensors'][0]['records'][0])) return null;
        return $data['sensors'][0]['records'][0];
    }

    private function updateFlowFromDelta($deviceId, $record)
    {
        // Puffer pro Gerät speichern
        $bufferRaw = $this->GetBuffer('last');
        $buffer = $bufferRaw ? @json_decode($bufferRaw, true) : array();
        if (!is_array($buffer)) $buffer = array();

        $key = ($deviceId !== '') ? $deviceId : 'default';
        $last = isset($buffer[$key]) ? $buffer[$key] : null;

        if ($last && isset($record['meterValue']) && isset($record['timestamp'])) {
            $dv = floatval($record['meterValue']) - floatval($last['meterValue']); // m³
            $dt = intval($record['timestamp']) - intval($last['timestamp']);      // s
            if ($dv >= 0 && $dt > 0) {
                // m³ → Liter: *1000; pro Minute: *60/dt
                $flowLMin = ($dv * 1000.0) * (60.0 / $dt);
                $this->MaintainVariable('FlowLMin', 'Durchfluss', VARIABLETYPE_FLOAT, 'HYDROP.FlowRate', 0, true);
                SetValueFloat($this->GetIDForIdent('FlowLMin'), $flowLMin);
            }
        }

        // Aktuelle Werte speichern
        if (isset($record['meterValue']) && isset($record['timestamp'])) {
            $buffer[$key] = array(
                'meterValue' => floatval($record['meterValue']),
                'timestamp'  => intval($record['timestamp'])
            );
            $this->SetBuffer('last', json_encode($buffer));
        }
    }

    private function buildEndpoint()
    {
        $base = rtrim($this->ReadPropertyString('BaseUrl'), '/');
        $path = '/' . ltrim($this->ReadPropertyString('EndpointPath'), '/');
        $meterId = trim($this->ReadPropertyString('MeterId'));
        if ($meterId !== '') {
            $path = str_replace(array('{meterId}', '{meterID}', '{id}'), $meterId, $path);
        }
        if ($base === '' || $path === '/') return '';
        return $base . $path;
    }

    private function apiGET($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        $headers = array('Accept: application/json');
        $name = trim($this->ReadPropertyString('AuthHeaderName'));
        $prefix = trim($this->ReadPropertyString('AuthHeaderPrefix'));
        $key = trim($this->ReadPropertyString('ApiKey'));
        if ($name !== '' && $key !== '') {
            $value = ($prefix !== '' ? ($prefix . ' ' . $key) : $key);
            $headers[] = $name . ': ' . $value;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception('cURL error: ' . $err);
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code < 200 || $code >= 300) {
            throw new Exception('HTTP ' . $code . ' for ' . $url . ' → ' . substr($resp, 0, 500));
        }
        return $resp;
    }

    // Optionaler Auto-Mapper (nur wenn EnableAutoMap=true)
    private function autoMapJson($data, $prefix = '')
    {
        if (!is_array($data)) return;
        foreach ($data as $k => $v) {
            $name = $prefix . (is_string($k) ? $k : strval($k));
            if (is_array($v)) {
                $this->autoMapJson($v, $name . '_');
            } else {
                if (is_numeric($v)) {
                    $ident = $this->sanitizeIdent($name);
                    $this->MaintainVariable($ident, $name, VARIABLETYPE_FLOAT, '', 0, true);
                    SetValueFloat($this->GetIDForIdent($ident), floatval($v));
                } elseif (is_bool($v)) {
                    $ident = $this->sanitizeIdent($name);
                    $this->MaintainVariable($ident, $name, VARIABLETYPE_BOOLEAN, '', 0, true);
                    SetValueBoolean($this->GetIDForIdent($ident), (bool)$v);
                } elseif (is_string($v) && strlen($v) <= 120) {
                    $ident = $this->sanitizeIdent($name);
                    $this->MaintainVariable($ident, $name, VARIABLETYPE_STRING, '', 0, true);
                    SetValueString($this->GetIDForIdent($ident), $v);
                }
            }
        }
    }

    private function sanitizeIdent($name)
    {
        $ident = preg_replace('/[^A-Za-z0-9_]/', '_', $name);
        if (strlen($ident) > 30) $ident = substr($ident, 0, 30);
        return $ident;
    }
}
