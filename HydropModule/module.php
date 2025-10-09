<?php
class HYDROP extends IPSModule
{
    public function Create()
    {
        parent::Create();
        // Konfigurations-Properties
        $this->RegisterPropertyString('BaseUrl', 'https://api.hydrop-systems.com');
        $this->RegisterPropertyString('AuthHeaderName', 'Authorization');
        $this->RegisterPropertyString('AuthHeaderPrefix', 'Bearer');
        $this->RegisterPropertyString('ApiKey', '');
        $this->RegisterPropertyString('EndpointPath', '/sensors/all/newest');
        $this->RegisterPropertyString('MeterId', '');
        $this->RegisterPropertyInteger('PollSeconds', 60);

        // Timer → ruft per RequestAction die Poll-Methode auf
        $this->RegisterTimer('PollTimer', 0, 'IPS_RequestAction($_IPS["TARGET"], "Poll", 0);');

        // Beispiel-Variablen (werden aktualisiert, wenn vorhanden)
        @$this->RegisterVariableFloat('Total', 'Total', '~Water');
        @$this->RegisterVariableFloat('Flow', 'Flow', '');
        @$this->RegisterVariableBoolean('Leak', 'Leak', '');
        @$this->RegisterVariableInteger('LastTimestamp', 'Last Timestamp', '~UnixTimestamp');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
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

            // Bekannte Felder versuchen
            $this->parseKnown($data);
            // Und alles weitere automatisch mappen
            $this->autoMapJson($data);

            $this->SetStatus(104);
        } catch (Exception $e) {
            $this->SendDebug('HYDROP Error', $e->getMessage(), 0);
            $this->SetStatus(202);
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

    private function parseKnown($data)
    {
        // Liste → nichts Spezielles, aber mappen
        if (isset($data[0]) && is_array($data[0])) {
            return; // Auto-Mapper übernimmt
        }

        // Einzelobjekt: versuche gängige Felder
        $total = $this->findNumber($data, array('total', 'total_m3', 'consumptionTotal', 'volumeTotal'));
        if ($total !== null && $this->GetIDForIdentSafe('Total')) {
            SetValueFloat($this->GetIDForIdent('Total'), floatval($total));
        }
        $flow = $this->findNumber($data, array('flow', 'flow_lpm', 'flowRate', 'currentFlow'));
        if ($flow !== null && $this->GetIDForIdentSafe('Flow')) {
            SetValueFloat($this->GetIDForIdent('Flow'), floatval($flow));
        }
        $leak = $this->findBool($data, array('leak', 'leakDetected', 'leakage'));
        if ($leak !== null && $this->GetIDForIdentSafe('Leak')) {
            SetValueBoolean($this->GetIDForIdent('Leak'), (bool)$leak);
        }
        $ts = $this->findAny($data, array('timestamp', 'ts', 'time', 'updatedAt'));
        if ($ts !== null && $this->GetIDForIdentSafe('LastTimestamp')) {
            if (is_numeric($ts)) {
                SetValueInteger($this->GetIDForIdent('LastTimestamp'), intval($ts));
            } elseif (is_string($ts)) {
                $unix = strtotime($ts);
                SetValueInteger($this->GetIDForIdent('LastTimestamp'), $unix ? $unix : time());
            }
        }
    }

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
                    if ($this->GetIDForIdentSafe($ident)) {
                        SetValueFloat($this->GetIDForIdent($ident), floatval($v));
                    }
                } elseif (is_bool($v)) {
                    $ident = $this->sanitizeIdent($name);
                    $this->MaintainVariable($ident, $name, VARIABLETYPE_BOOLEAN, '', 0, true);
                    if ($this->GetIDForIdentSafe($ident)) {
                        SetValueBoolean($this->GetIDForIdent($ident), (bool)$v);
                    }
                } elseif (is_string($v) && strlen($v) <= 120) {
                    $ident = $this->sanitizeIdent($name);
                    $this->MaintainVariable($ident, $name, VARIABLETYPE_STRING, '', 0, true);
                    if ($this->GetIDForIdentSafe($ident)) {
                        SetValueString($this->GetIDForIdent($ident), $v);
                    }
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

    private function findNumber($arr, $keys)
    {
        foreach ($keys as $k) {
            if (isset($arr[$k]) && is_numeric($arr[$k])) return $arr[$k];
        }
        return null;
    }

    private function findAny($arr, $keys)
    {
        foreach ($keys as $k) {
            if (isset($arr[$k])) return $arr[$k];
        }
        return null;
    }

    private function findBool($arr, $keys)
    {
        foreach ($keys as $k) {
            if (isset($arr[$k]) && is_bool($arr[$k])) return $arr[$k];
            if (isset($arr[$k]) && ($arr[$k] === 0 || $arr[$k] === 1)) return (bool)$arr[$k];
        }
        return null;
    }

    // Helfer, um Existenz einer Variable sicher zu prüfen
    private function GetIDForIdentSafe($ident)
    {
        try { $id = $this->GetIDForIdent($ident); return ($id > 0); } catch (Exception $e) { return false; }
    }
}
