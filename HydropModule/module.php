<?php
declare(strict_types=1);
class HYDROP extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // Properties
        $this->RegisterPropertyString('BaseUrl', 'https://api.hydrop-systems.com');
        $this->RegisterPropertyString('AuthHeaderName', 'Authorization');
        $this->RegisterPropertyString('AuthHeaderPrefix', 'Bearer');
        $this->RegisterPropertyString('ApiKey', '');
        $this->RegisterPropertyString('MeterId', '');
        $this->RegisterPropertyString('EndpointPath', '/api/v1/devices');
        $this->RegisterPropertyInteger('PollSeconds', 300);

        // Timer → ruft über RequestAction die Poll-Methode dieser Instanz auf
        $this->RegisterTimer('PollTimer', 0, 'IPS_RequestAction($_IPS["TARGET"], "Poll", 0);');

        // Bekannte Variablen
        $this->RegisterVariableFloat('Total', 'Total', '~Water');
        $this->RegisterVariableFloat('Flow', 'Flow', '');
        $this->RegisterVariableBoolean('Leak', 'Leak', '');
        $this->RegisterVariableInteger('LastTimestamp', 'Last Timestamp', '~UnixTimestamp');

        $this->SetStatus(201);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $ok = $this->isConfigComplete();
        $this->SetTimerInterval('PollTimer', $ok ? $this->ReadPropertyInteger('PollSeconds') * 1000 : 0);
        $this->SetStatus($ok ? 104 : 201);
    }

    // Wird von der Form-Action und vom Timer verwendet
    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'TestOnce':
                $this->TestOnce();
                return true;
            case 'Poll':
                $this->Poll();
                return true;
            default:
                throw new Exception('Invalid action: ' . $Ident);
        }
    }

    public function TestOnce()
    {
        $this->SendDebug('Action', 'Manual test triggered', 0);
        $this->Poll();
    }

    public function Poll()
    {
        if (!$this->isConfigComplete()) {
            $this->SendDebug('Poll', 'Configuration incomplete', 0);
            $this->SetStatus(201);
            return;
        }

        try {
            $json = $this->apiGET($this->buildEndpoint());
            $this->SendDebug('Response', substr($json, 0, 4000), 0);
            $data = json_decode($json, true);
            if ($data === null) {
                throw new Exception('Invalid JSON');
            }

            $parsed = $this->parseKnown($data);
            if (!$parsed) {
                $this->autoMapJson($data);
            }
            $this->SetStatus(104);
        } catch (Throwable $e) {
            $this->SendDebug('Error', $e->getMessage(), 0);
            $this->SetStatus(202);
        }
    }

    private function buildEndpoint(): string
    {
        $base = rtrim($this->ReadPropertyString('BaseUrl'), '/');
        $path = '/' . ltrim($this->ReadPropertyString('EndpointPath'), '/');
        $meterId = trim($this->ReadPropertyString('MeterId'));
        $path = str_replace(['{meterId}', '{meterID}', '{id}'], $meterId !== '' ? $meterId : '', $path);
        return $base . $path;
    }

    private function apiGET(string $url): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        // SSL-Checks standardmäßig aktiv (Produktion)

        $headers = ['Accept: application/json'];
        $name = trim($this->ReadPropertyString('AuthHeaderName'));
        $prefix = trim($this->ReadPropertyString('AuthHeaderPrefix'));
        $key = trim($this->ReadPropertyString('ApiKey'));
        if ($name !== '' && $key !== '') {
            $value = $prefix !== '' ? ($prefix . ' ' . $key) : $key;
            $headers[] = $name . ': ' . $value;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $resp = curl_exec($ch);
        if ($resp === false) {
            throw new Exception('cURL error: ' . curl_error($ch));
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code < 200 || $code >= 300) {
            throw new Exception('HTTP ' . $code . ' for ' . $url . ' → ' . substr($resp, 0, 500));
        }
        return $resp;
    }

    private function parseKnown($data): bool
    {
        // Liste → map
        if (isset($data[0]) && is_array($data[0])) {
            $this->autoMapJson($data);
            return true;
        }

        if (is_array($data)) {
            $total = $this->findNumber($data, ['total', 'total_m3', 'consumptionTotal', 'volumeTotal']);
            if ($total !== null) {
                $this->SetValue('Total', (float)$total);
            }
            $flow = $this->findNumber($data, ['flow', 'flow_lpm', 'flowRate', 'currentFlow']);
            if ($flow !== null) {
                $this->SetValue('Flow', (float)$flow);
            }
            $leak = $this->findBool($data, ['leak', 'leakDetected', 'leakage']);
            if ($leak !== null) {
                $this->SetValue('Leak', (bool)$leak);
            }
            $ts = $this->findNumber($data, ['timestamp', 'ts', 'time', 'updatedAt']);
            if ($ts !== null) {
                if (is_numeric($ts)) {
                    $this->SetValue('LastTimestamp', (int)$ts);
                } elseif (is_string($ts)) {
                    $this->SetValue('LastTimestamp', strtotime($ts) ?: time());
                }
            }
            $this->autoMapJson($data);
            return true;
        }
        return false;
    }

    private function autoMapJson($data, string $prefix = ''): void
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $name = $prefix . (is_string($k) ? $k : (string)$k);
                if (is_array($v)) {
                    $this->autoMapJson($v, $name . '_');
                } else {
                    if (is_numeric($v)) {
                        $ident = $this->sanitizeIdent($name);
                        $this->MaintainVariable($ident, $name, VARIABLETYPE_FLOAT, '', 0, true);
                        $this->SetValue($ident, (float)$v);
                    } elseif (is_bool($v)) {
                        $ident = $this->sanitizeIdent($name);
                        $this->MaintainVariable($ident, $name, VARIABLETYPE_BOOLEAN, '', 0, true);
                        $this->SetValue($ident, (bool)$v);
                    } elseif (is_string($v)) {
                        if (strlen($v) <= 120) {
                            $ident = $this->sanitizeIdent($name);
                            $this->MaintainVariable($ident, $name, VARIABLETYPE_STRING, '', 0, true);
                            $this->SetValue($ident, $v);
                        }
                    }
                }
            }
        }
    }

    private function sanitizeIdent(string $name): string
    {
        $ident = preg_replace('/[^A-Za-z0-9_]/', '_', $name);
        return substr($ident, 0, 30);
    }

    private function findNumber(array $arr, array $keys)
    {
        foreach ($keys as $k) {
            if (isset($arr[$k]) && is_numeric($arr[$k])) return $arr[$k];
        }
        return null;
    }

    private function findBool(array $arr, array $keys)
    {
        foreach ($keys as $k) {
            if (isset($arr[$k]) && is_bool($arr[$k])) return $arr[$k];
            if (isset($arr[$k]) && ($arr[$k] === 0 || $arr[$k] === 1)) return (bool)$arr[$k];
        }
        return null;
    }

    private function isConfigComplete(): bool
    {
        if (trim($this->ReadPropertyString('BaseUrl')) === '') return false;
        if (trim($this->ReadPropertyString('ApiKey')) === '') return false;
        if (trim($this->ReadPropertyString('EndpointPath')) === '') return false;
        return true;
    }
}
