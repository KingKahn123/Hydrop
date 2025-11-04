# HYDROP für IP-Symcon

Ein IP-Symcon-Modul zur Integration von **HYDROP-Wasserzählern** über die offizielle REST-API (**[https://api.hydrop-systems.com](https://api.hydrop-systems.com)**)

Das Modul liest aktuelle Messdaten (Gesamtverbrauch, Durchfluss, Zeitstempel, Gerät) aus und stellt sie als Variablen in IP-Symcon bereit.
---

## Funktionen

- Automatische Abfrage der HYDROP-REST-API in festen Intervallen  
- Messwerte:
  - **Gesamtverbrauch (m³)**
  - **Durchfluss (L/min)** – berechnet aus Zählerdifferenz über die Zeit  
  - **Zeitstempel (Unixzeit)**
  - **Gerät (ID / Name)**
- Optionale automatische Variablen-Erstellung (Auto-Mapper)  
- Konfigurierbarer API-Zugriff (URL, Header, Key, Endpoint, Intervall)  
- Unterstützt mehrere Zähler durch separate Instanzen  
- Manuelle Testabfrage und Timersteuerung direkt im Formular  
- Korrekte Einheiten dank eigener Variablenprofile (`m³`, `L/min`)

---

## Installation (über den IP-Symcon Modul Store)

1. In der IP-Symcon-Verwaltungskonsole öffnen:  
   **Module Store**
2. Nach **HYDROP** suchen  
3. Modul **installieren**
4. Neue Instanz anlegen:  
   **Objekt hinzufügen → Instanz → HYDROP**

> Updates erfolgen automatisch über den Modul Store.

---

## Konfiguration

| Feld | Beschreibung |
|------|---------------|
| **Base URL** | Standard: `https://api.hydrop-systems.com` |
| **Auth Header Name** | `apikey` |
| **Auth Header Prefix** | leer lassen |
| **API Key** | Dein persönlicher API-Key |
| **Meter ID (optional)** | Wird automatisch ersetzt, falls im Pfad `{meterId}` vorkommt |
| **Endpoint Path** | z. B. `/sensors/all/newest` |
| **Alle JSON-Felder automatisch anlegen** | (Checkbox) legt zusätzlich alle Felder der JSON-Antwort als Variablen an |
| **Poll-Intervall (Sekunden)** | Zeit zwischen automatischen Abfragen (≥ 10 s) |

---

## Bedienung

**Buttons im Formular:**
- **Testen (einmal abfragen)** → ruft den Endpoint testweise einmal sofort ab  
- Der Poll-Timer startet nach „Änderungen übernehmen“ automatisch.

---

## Berechnete Werte

| Variable | Einheit | Beschreibung |
|-----------|----------|--------------|
| `Gesamtverbrauch` | m³ | Aktueller Gesamtzählerstand |
| `Zeitstempel` | Unix-Zeit | Zeitpunkt der Messung |
| `Gerät` | – | ID/Name aus der API |
| `Durchfluss` | L/min | Berechnet aus ΔZählerstand / ΔZeit |

Berechnungsformel:
```
Δm³ = aktueller_meterValue - letzter_meterValue
Δt = aktueller_timestamp - letzter_timestamp
Durchfluss [L/min] = (Δm³ * 1000) * (60 / Δt)
```

Der Durchflusswert erscheint **ab dem zweiten erfolgreichen Poll**.

---

## 🧠 Hinweise

- HYDROP-API nutzt Header `apikey: <KEY>` (kein Bearer-Token).  

---

## 📄 Modulstruktur

```
library.json // Bibliotheksdefinition
HydropModule/
├── module.json // Instanzdefinition
├── module.php // Logik & API-Aufrufe
├── form.json // Konfigurationsformular
└── locale.json // Übersetzungen (de/en)
```

---

## 🧑‍💻 Autor & Lizenz

| Feld | Info |
|------|------|
| **Autor:** | Kai Stockmann |
| **Version:** | 1.1 |
| **Kompatibel mit:** | IP-Symcon ≥ 7.0 |
| **Lizenz:** | MIT |
| **Repository:** | https://github.com/KingKahn123/Hydrop.git |

