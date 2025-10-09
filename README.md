 HYDROP (REST) – IP‑Symcon Modul

Dieses Modul ruft Daten der HYDROP REST API ab und legt/aktualisiert Variablen in IP‑Symcon. Es ist generisch konfigurierbar (Header, Prefix, Pfade) und enthält einen Auto‑Mapper für JSON.

## Einrichtung
1. Dateien in ein neues Modulverzeichnis unter `modules/Hydrop/` legen oder eigenes Git-Repo verwenden und in IP‑Symcon als Modul hinzufügen.
2. Instanz erstellen und konfigurieren (Base URL, Header, Key, Endpoint, Intervall).
3. Mit **Testen** überprüfen, welche Variablen angelegt werden; ggf. `MeterId` setzen und `EndpointPath` auf den Messwerte‑Endpoint anpassen.

## Authentifizierung
- Trage den Header‑Namen (z. B. `Authorization` oder `x-api-key`) und ggf. ein Präfix (z. B. `Bearer`) in der Instanz ein. Der Request enthält dann `HeaderName: <Prefix> <ApiKey>`.

## Beispiel‑Endpoints (bitte API‑Doku prüfen)
- Liste Geräte: `/api/v1/devices`
- Letzte Messwerte: `/api/v1/devices/{meterId}/measurements?limit=1`

## Variablen
- **Total** – Gesamtverbrauch (m³)
- **Flow** – Durchfluss (z. B. L/min)
- **Leak** – Leckage erkannt (bool)
- **Last Timestamp** – Zeitstempel der letzten Messung
- Weitere Felder werden automatisch aus dem JSON gemappt.

## Fehleranalyse
- Instanz‑Status **202**: HTTP/Parser‑Fehler → Debug‑Log prüfen.
- **201**: Konfiguration unvollständig.

## Anpassungen
- In `parseKnown()` kannst du Keys auf die echten API‑Felder mappen.
- In `autoMapJson()` werden alle numerischen/boolean Felder rekursiv als Variablen angelegt.

Lizenz: MIT
