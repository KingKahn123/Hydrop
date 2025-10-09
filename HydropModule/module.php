<?php
class HYDROP extends IPSModule
{
    public function Create()
    {
        parent::Create();
        // Einfacher Poll-Timer (der später Daten abruft)
        $this->RegisterTimer('PollTimer', 0, 'IPS_RequestAction($_IPS["TARGET"], "Poll", 0);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        // Timer z. B. alle 60 Sekunden
        $this->SetTimerInterval('PollTimer', 60000);
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Poll':
                $this->Poll();
                break;
            default:
                throw new Exception('Unknown action ' . $Ident);
        }
    }

    private function Poll()
    {
        $this->SendDebug('HYDROP', 'Timer executed at ' . date('Y-m-d H:i:s'), 0);
    }
}
