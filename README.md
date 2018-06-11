# Hydrawise

Modul für IP-Symcon ab Version 4.4

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)  
2. [Voraussetzungen](#2-voraussetzungen)  
3. [Installation](#3-installation)  
4. [Funktionsreferenz](#4-funktionsreferenz)
5. [Konfiguration](#5-konfiguration)  
6. [Anhang](#6-anhang)  

## 1. Funktionsumfang

Es gibt ein System _Hydrawise_ zur Bewässerungssteuerung (https://www.hydrawise.com); _Hydrawise_ wurde vor einigen Jahren von _Fa. Hunter_ gekauft.<br>
Es ist ein Bewässerungssystem, das über eine kleine Appliance die Bewässerung steuert, bei der Berechnung der Anpassung der Bewässerungszeiten an lokale klimatische Bedingungen (Regenmenge sowie Verdunstung) über das Internet auf eine Hydrawise-eigenen Steuerung zugreift. Die Wetterinformationen bezieht das System lokalen Wetterstationen, die dem Controller zugeordnete werden (öffentliche Wetterstationen und bei Wunderground registrierte Stationen).
Für die Administration gibt es eine App und einen Zugriff über einen Browser mit identischen Funktionsumfang.
Es gibt Ausbaustufen von 6-36 Bewässerungskreise und 2 Sensoren.

Es gibt eine (einfache) REST-API für eine manuelle Steuerung der Bewässerung und ein paar Status-Informationen.

Das Modul ermögliche die Kommunikation über diese API um Daten zu speichern, einige Werte zu berechnen und die vorhandenen Steuermöglichkeiten anzubieten.

Das Modul besteht aus folgenden Instanzen

## 2. Voraussetzungen

 - IP-Symcon ab Version 4.4
 - Bewässerungscomputer Hydrawise von Fa. Hunter

## 3. Installation

### a. Laden des Moduls

Die Konsole von IP-Symcon öffnen. Im Objektbaum unter Kerninstanzen die Instanz __*Modules*__ durch einen doppelten Mausklick öffnen.

In der _Modules_ Instanz rechts oben auf den Button __*Hinzufügen*__ drücken.
 
In dem sich öffnenden Fenster folgende URL hinzufügen:

`https://github.com/demel42/IPSymconHydrawise.git`
    
und mit _OK_ bestätigen.    
        
Anschließend erscheint ein Eintrag für das Modul in der Liste der Instanz _Modules_    

### b. Einrichtung in IPS

In IP-Symcon nun unterhalb von _I/O Instanzen_ die Funktion _Instanz hinzufügen_ (_CTRL+1_) auswählen, als Hersteller _Hunter_ und als Gerät _Hydrawise I/O_ auswählen.

In dem Konfigurationsdialog die Hydrawise-Zugangsdaten eintragen; eine Überprüfung kann durch die Schaltfläche _Aktualisiere Daten_ durchgeführt werden. Ein relativ kurzes Aktualisierungsintervall ist leider erforderlich, weil bestimmte Informationen nur kurzzeitig zur Verfügung stehen.

Dann unter _Konfigurator Instanzen_ analog den Konfigurator _Hydrawise Konfigurator_ hinzufügen. Hier werden alle Controller zu dem Account angeboten; für den ausgewählten Controller werden dann bei Betätigen der Schaltfläche _Importieren des Controllers_ alle Sensoren- und Zonen-Instanzen angelegt.
Bei einer erneuten Betätigung dieser Funktion werden eventuelle gelöschte Instanzen wieder angelegt.

## 4. Funktionsreferenz

### zentrale Funktion

`HydrawiseIO_UpdateData(int $InstanzID)`

ruft die Daten von Hydrawise ab. Wird automatisch zyklisch durch die Instanz durchgeführt im Abstand wie in der Konfiguration angegeben. Die Daten werden an _HydrawiseController_ weitergeleitet und von dort an _HydrawiseSensor_ und _HydrawiseZone_.

## 5. Konfiguration

### HydrawiseIO

Hierüber findet die http-Kommunikation statt.

#### Variablen

| Eigenschaft               | Typ      | Standardwert | Beschreibung |
| :-----------------------: | :-----:  | :----------: | :----------------------------------------------------------------------------------------------------------: |
| Hydrawise-Zugangsdaten    | string   |              | Benutzername und Passwort von https://app.hydrawise.com/config/login                                         |
|                           |          |              | |
| UpdateDataInterval        | integer  | 60           | Angabe in Sekunden                          |

#### Schaltflächen

| Bezeichnung                  | Beschreibung |
| :--------------------------: | :------------------------------------------------: |
| Aktualisiere Daten           | führt eine sofortige Aktualisierung durch |

### HydrawiseConfig

Konfigurator zur Anlage des Controllers, der Sensoren und Zonen (Bewässerungskreise).

#### Auswahl

Es werden alle Controller zu dem konfigurierten Account zur Auswahl angeboten.

#### Schaltflächen

| Bezeichnung                  | Beschreibung |
| :--------------------------: | :------------------------------------------------: |
| Import des Controller        | richtet die Geräte-Instanzen ein |
  
### HydrawiseController

Stellt den Bewässerungscomputer (_HC-6_ oder _HC-12_) dar. Hier werden übergreifen Daten gespeichert. 
An dieser Stekke gibt es auch eine HTML-Boy sowie ein WebHook zur Darstellung von Infortaionen zum Gesamtsystem.
Prop Account können mehrere Bewässerungseinheiten angelegt werden, das wären dann getrennte _HydrawiseController_.

#### Properties

werden vom Konfigurator beim Anlegen der Instanz gesetzt.

| Eigenschaft            | Typ     | Standardwert | Beschreibung                                  |
| :--------------------: | :-----: | :----------: | :-------------------------------------------: |
| with_last_contact      | boolean | true         | letzter Kontakt mit Hydrawise                 |
| with_last_message      | boolean | false        | eventuell Nachricht zu der letzten Kommunikation |
| with_info              | boolean | true         | Informationen zur Gesamt-Bewässerungszeit etc. |
| with_observations      | boolean | true         | Wetterbeobachtungen (der verknüpften Wetterstationen) |
| num_forecast           | integer | 0            | Wettervorhersage (__0__=_keine_, __1__=_heute_, __2__=_morgen_, __3__=_übermorgen_ |
| with_status_box        | boolean | false        | HTML-Box mit einer Zusammenfassung der altuellen Bewässerung |
| with_daily_value       | boolean | true         | Ermittlung von Tageswerten für Gesamtbewässerungszeit |
|                        |         |              |                                               |
| statusbox_script       | integer | 0            | Script zum Füllen der Variable _StatusBox_    |
| webhook_script         | integer | 0            | Script zur Verwendung im WebHook              |
|                        |         |              |                                               |
| minutes2fail           | integer | 30           | Dauer, bis die Kommunikation als gestört gilt |

Das hier angebbare Minuten-Intervall dient zu Überprüfung der Kommunikation zwischen dem Controller und dem Hydrawise-Server.
  ist die Zeit überschritten, wird die Variable _Status_ des Controllers auf Fehler gesetzt.
Anmerkung: die Variable _Status_ wird auch auf Fehler gesetzt wenn das IO-Modul einen Fehler feststellt.

Erläuterung zu _statusbox_script_, _webhook_script_:
  mit diesen Scripten kann man eine alternative Darstellung realisieren.

Ein passendes Code-Fragment für ein Script:
```
$data = HydrawiseController_GetRawData($_IPS['InstanceID']);
if ($data) {
    $station = json_decode($r,true);
    ...
    echo $result;
}
```
Die Beschreibung der Struktur siehe _HydrawiseController_GetRawData()_.

Beispiel in module.php sind _Build_StatusBox()_ und _ProcessHook_Status()_.

#### Statusvariablen

es werden einige Variable angelegt, zum Teil optional. Zur Erklärung:
- _DailyReference_, ist ein UNIX-Timestamp, der das Datum enthält, auf den sich die Tageswerte beziehen. Wird automatisch bei dem ersten Nachricht nach Mitternacht auf den aktuellen Tag gestellt.
Betrifft die Variablen, die mit _Daily_ beginnen.
- _Obs*_: sind die Wetterbeobachtungen
- _Forecast*_: sind die Wettervorhersagen

### HydrawiseSensor

Entspricht den bis zu 2 Sensoren; bisher unterstützt wird der Typ _flow meter_, also dem Durchflussmesser für den Wasserverbrauch.

#### Properties

| Eigenschaft            | Typ     | Standardwert | Beschreibung                                              |
| :--------------------: | :-----: | :----------: | :-------------------------------------------------------: |
| controller_id          | string  |              | interne ID des Controllers, wird vom Konfigurator gefüllt |
| connector              | integer |              | Anschluss am Controller                                   |
| model                  | integer |              | Modell des Sensors                                        |
|                        |         |              |                                                           |
| with_daily_value       | boolean | true         | Tageswerte                                                |

#### Statusvariablen

- _Flow_: Wert eines Sensors vom Typ _flow meter_, als Angabe _letzte Woche_ (was auch immer da genau ist).
- _DailyFlow_: errechneter Tageswert aus _Flow_.

### HydrawiseZone

Das sind die einzelnen Bewässerungskreise, hier sind alle Zonen-spezifischen Daten abgelegt.

#### Properties

| Eigenschaft            | Typ     | Standardwert | Beschreibung                                              |
| :--------------------: | :-----: | :----------: | :-------------------------------------------------------: |
| controller_id          | string  |              | interne ID des Controllers, wird vom Konfigurator gefüllt |
| relay_id               | string  |              | interne ID der Zone, wird vom Konfigurator gefüllt        |
| connector              | integer |              | Anschluss am Controller                                   |
|                        |         |              |                                                           |
| with_daily_value       | boolean | true         | Tageswerte                                                |
|                        |         |              |                                                           |
| visibility_script      | integer | 0            | Script um die Sichtbarkeit von Variablen zu steuern       |

Erläuterung zu _visibility_script_: diese optionale Script ermöglicht es dem Anwender, Variablen in Abhängigkeit von Variable auszublenden (z.B. keine Rest-Bewässerungsdauer, wenn keine Bewässerung aktiv ist). Ein Muster eines solchen Scriptes ist _libs/HydrawiseVisibility.php_.

#### Statusvariablen

_LastRun_, _NextRun_: letzter bzw. nächster geplanten Zyklus.

_Daily*_: Tageswerte

#### editerbare Statusvariablen

_ZoneAction_: Stoppen und Starten eines Bewässerungzyklus (siehe Variablenprofil _Hydrawise.ZoneAction_).

_SuspendAction_: Bewässerung aussetzen bzw. eine Rücknahme der Aussetzung (siehe Variablenprofil _Hydrawise.SuspendAction_).

_SuspendUntil_: Ausgabe einer aktuellen Aussetzung der Bewässerung als auch die Möglichkeit, eine neuen End-Zeitpunkt anzugeben.

### Variablenprofile

Es werden folgende Variableprofile angelegt:
* Integer<br>
  - Hydrawise.ZoneAction; ist standrdmässig mit folgenden Ausprägungen angelegt: Stop, Voreinstellung, 1 min, ...<br>
Eine Anpassung an eigene Bedürfnisse ist möglich, der Wert der Assoziation ist die zu verwendende Bewässerungsdater in Minuten.
  - Hydrawise.ZoneSuspend;  ist standrdmässig mit folgenden Ausprägungen angelegt: Löschen, 1 Tag, ...<br>
Eine Anpassung an eigene Bedürfnisse ist möglich, der Wert der Assoziation ist die Dauer der Aussetzung der Bewässerung in Tagen
  - Hydrawise.Duration, Hydrawise.ProbabilityOfRain, Hydrawise.WaterSaving

* Float<br>
Hydrawise.Flowmeter, Hydrawise.Humidity, Hydrawise.Rainfall, Hydrawise.Temperatur, Hydrawise.WindSpeed

### Funktionen

`bool HydrawiseZone_Run(int $InstanzID, int $duration = null)`

startet die Bewässerung dieser Zone für eine bestimmte Zeit (in Minuten). Ist _duration_ nicht angegeben bzw. _null_, wird die in Hydrawise für die Zone aktuell ermittelte Dauer verwendet.

`bool HydrawiseZone_Stop(int $InstanzID)`

stoppt eine laufende Bewässerung.

`bool HydrawiseZone_Suspend(int $InstanzID, int $timestamp)`

setzt die Bewässerung bis zum angegebenen Zeitpunkt aus.

`bool HydrawiseZone_Resume(int $InstanzID)`

aktiviet wieder die normale Bewässerung.

## 6. Anhang

GUIDs
- Modul: `{CCB22FB7-9262-4387-98E4-256A07E37816}` 
- Instanzen:
  - HydrawiseIO: `{5927E05C-82D0-4D78-B8E0-A973470A9CD3}`
  - HydrawiseConfig: `{92DEBBAA-3191-4FA8-AB36-ED82BEA08154}`
  - HydrawiseController: `{B1B47A68-CE20-4887-B00C-E6412DAD2CFB}`
  - HydrawiseZone: `{6A0DAE44-B86A-4D50-A76F-532365FD88AE}`
  - HydrawiseSensor: `{56D9EFA4-8840-4DAE-A6D2-ECE8DC862874}`
- Nachrichten:
  - `{A717FCDD-287E-44BF-A1D2-E2489A4C30B2}`: an HydrawiseConfig, HydrawiseController
  - `{B54B579C-3992-4C1D-B7A8-4A129A78ED03}`: an HydrawiseIO
  - `{5BF2F1ED-7782-457B-856F-D4F388CBF060}`: an HydrawiseSensor, HydrawiseZone
  - `{5361495C-0EF7-4319-8D2C-BEFA5BCC7F25}`: an HydrawiseController
