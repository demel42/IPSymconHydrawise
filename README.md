# IPSymconHydrawise

[![IPS-Version](https://img.shields.io/badge/Symcon_Version-5.3+-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Module-Version](https://img.shields.io/badge/Modul_Version-1.19-blue.svg)
![Code](https://img.shields.io/badge/Code-PHP-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)  
2. [Voraussetzungen](#2-voraussetzungen)  
3. [Installation](#3-installation)  
4. [Funktionsreferenz](#4-funktionsreferenz)
5. [Konfiguration](#5-konfiguration)  
6. [Anhang](#6-anhang)  
7. [Versions-Historie](#7-versions-historie)

## 1. Funktionsumfang

Es gibt ein System _Hydrawise_ zur Bewässerungssteuerung (https://www.hydrawise.com); _Hydrawise_ wurde vor einigen Jahren von _Fa. Hunter_ gekauft.<br>
Es ist ein Bewässerungssystem, das über eine kleine Appliance die Bewässerung steuert, bei der Berechnung der Anpassung der Bewässerungszeiten an lokale klimatische Bedingungen (Regenmenge sowie Verdunstung) über das Internet auf eine Hydrawise-eigenen Steuerung zugreift. Die Wetterinformationen bezieht das System lokalen Wetterstationen, die dem Controller zugeordnete werden (öffentliche Wetterstationen und bei Wunderground registrierte Stationen).
Für die Administration gibt es eine App und einen Zugriff über einen Browser mit identischen Funktionsumfang.
Es gibt Ausbaustufen von 6-36 Bewässerungskreise und 2 Sensoren.

Es gibt eine (einfache) REST-API für eine manuelle Steuerung der Bewässerung und ein paar Status-Informationen.

Das Modul ermögliche die Kommunikation über diese API um Daten zu speichern, einige Werte zu berechnen und die vorhandenen Steuermöglichkeiten anzubieten.

Das Modul besteht aus folgenden Instanzen

## 2. Voraussetzungen

 - IP-Symcon ab Version 5.3<br>
   Version 4.4 mit Branch _ips_4.4_ (nur noch Fehlerkorrekturen)
 - Bewässerungscomputer Hydrawise von Fa. Hunter

## 3. Installation

### a. Laden des Moduls

Die Konsole von IP-Symcon öffnen. Im Objektbaum unter Kerninstanzen die Instanz __*Modules*__ durch einen doppelten Mausklick öffnen.

In der _Modules_ Instanz rechts oben auf den Button __*Hinzufügen*__ drücken.
 
In dem sich öffnenden Fenster folgende URL hinzufügen:

`https://github.com/demel42/IPSymconHydrawise.git`
    
und mit _OK_ bestätigen. Ggfs. auf anderen Branch wechseln (Modul-Eintrag editieren, _Zweig_ auswählen).
        
Anschließend erscheint ein Eintrag für das Modul in der Liste der Instanz _Modules_    

### b. Einrichtung in IPS

In IP-Symcon nun unterhalb von _I/O Instanzen_ die Funktion _Instanz hinzufügen_ (_CTRL+1_) auswählen, als Hersteller _Hunter_ und als Gerät _Hydrawise I/O_ auswählen.

In dem Konfigurationsdialog die Hydrawise-Zugangsdaten eintragen; eine Überprüfung kann durch die Schaltfläche _Aktualisiere Daten_ durchgeführt werden. Ein relativ kurzes Aktualisierungsintervall ist leider erforderlich, weil bestimmte Informationen nur kurzzeitig zur Verfügung stehen.

Dann unter _Konfigurator Instanzen_ analog den Konfigurator _Hydrawise Konfigurator_ hinzufügen. Hier werden alle Controller zu dem Account angeboten; für den ausgewählten Controller werden dann bei Betätigen der Schaltfläche _Importieren des Controllers_ alle Sensoren- und Zonen-Instanzen angelegt.
Bei einer erneuten Betätigung dieser Funktion werden eventuelle gelöschte Instanzen wieder angelegt.

## 4. Funktionsreferenz

### zentrale Funktion

`HydrawiseIO_UpdateData(int $InstanzID)`

ruft die Daten von Hydrawise ab. Wird automatisch zyklisch durch die Instanz durchgeführt im Abstand wie in der Konfiguration angegeben. Die Daten werden an _HydrawiseController_ weitergeleitet und von dort an _HydrawiseSensor_ und _Hydrawise_.

## 5. Konfiguration

### HydrawiseIO

Hierüber findet die http-Kommunikation statt.

#### Properties

| Eigenschaft              | Typ     | Standardwert | Beschreibung |
| :----------------------- | :------ | :----------- | :----------- |
| Instanz ist deaktiviert  | boolean | false        | Instanz temporär deaktivieren |
|                          |         |              | |
| Hydrawise-Zugangsdaten   | string  |              | Benutzername und Passwort von https://app.hydrawise.com/config/login |

#### Schaltflächen

| Bezeichnung             | Beschreibung |
| :---------------------- | :----------- |
| Zugangsdaten überprüfen | führt eine sofortige Aktualisierung durch |

### HydrawiseConfig

#### Properties

| Eigenschaft            | Typ     | Standardwert | Beschreibung |
| :--------------------- | :------ | :----------- | :----------- |
| Controller             |         |              | Konfigurator zur Anlage eines Controller zu diesem Account |

Pro Account können mehrere Bewässerungseinheiten angelegt werden, das sind dann getrennte _HydrawiseController_.

### HydrawiseController

Stellt den Bewässerungscomputer (_HC-6_ oder _HC-12_) dar. Hier werden übergreifen Daten gespeichert. 
An dieser Stekke gibt es auch eine HTML-Box sowie ein WebHook zur Darstellung von Infortaionen zum Gesamtsystem.
Weiterhin werden in diesem Modul die Sensoren und Zonen (Bewässerungskreise) dieses Controllers zu Erzeugung angeboten.

#### Properties

werden vom Konfigurator beim Anlegen der Instanz gesetzt.

| Eigenschaft            | Typ     | Standardwert    | Beschreibung |
| :--------------------- | :------ | :-----------    | :----------- |
| controller_id          | string  |                 | interne ID des Controllers, wird vom Konfigurator gefüllt |
|                        |         |                 | |
| with_last_contact      | boolean | true            | letzter Kontakt mit Hydrawise |
| with_last_message      | boolean | false           | eventuell Nachricht zu der letzten Kommunikation |
| with_info              | boolean | true            | Informationen zur Gesamt-Bewässerungszeit etc. |
| with_observations      | boolean | true            | Wetterbeobachtungen (der verknüpften Wetterstationen) |
| num_forecast           | integer | 0               | Wettervorhersage (__0__=_keine_, __1__=_heute_, __2__=_morgen_, __3__=_übermorgen_ |
| with_status_box        | boolean | false           | HTML-Box mit einer Zusammenfassung der altuellen Bewässerung |
| with_daily_value       | boolean | true            | Ermittlung von Tageswerten für Gesamtbewässerungszeit |
|                        |         |                 | |
| statusbox_script       | integer | 0               | Script zum Füllen der Variable _StatusBox_ |
| hook                   | string  | /hook/Hydrawise | bei mehreren Controller müssen die Webhook unterschiedlich heissen |
| webhook_script         | integer | 0               | Script zur Verwendung im WebHook |
|                        |         |                 | |
|                        |         |                 | |
| Aktualisiere Daten ... | integer | 60              | Aktualisierungsintervall, Angabe in Sekunden |
|                        |         |                 | |
| minutes2fail           | integer | 30              | Dauer, bis die Kommunikation als gestört gilt |
|                        |         |                 | |
| Sensoren und Zonen     |         |                 | Konfigurator zur Anlage der Komponenten dieses Controllers |

Erläuterung zu _minutes2fail_: Das hier angebbare Minuten-Intervall dient zu Überprüfung der Kommunikation zwischen dem Controller und dem Hydrawise-Server.
Ist die Zeit überschritten, wird die Variable _Status_ des Controllers auf Fehler gesetzt.
Anmerkung: die Variable _Status_ wird auch auf Fehler gesetzt wenn das IO-Modul einen Fehler feststellt.

Erläuterung zu _statusbox_script_, _webhook_script_:
Mit diesen Scripten kann man eine alternative Darstellung realisieren.

Ein passendes Code-Fragment für ein Script:
```
$data = Hydrawise_GetRawData($_IPS['InstanceID']);
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

| Eigenschaft      | Typ     | Standardwert | Beschreibung |
| :--------------- | :------ | :----------- | :----------- |
| controller_id    | string  |              | interne ID des Controllers, wird vom Konfigurator gefüllt |
| connector        | integer |              | Anschluss am Controller |
| model            | integer |              | Modell des Sensors |
|                  |         |              | |
| with_flowrate    | boolean | true         | Darstellung der aktuellen Wasser-Durchlaufmenge |
| with_daily_value | boolean | true         | Tageswerte |

#### Statusvariablen

- _Flow_: Wert eines Sensors vom Typ _flow meter_, als Angabe _letzte Woche_ (was auch immer da genau ist).
- _DailyFlow_: errechneter Tageswert aus _Flow_.

### HydrawiseZone

Das sind die einzelnen Bewässerungskreise, hier sind alle Zonen-spezifischen Daten abgelegt.

#### Properties

| Eigenschaft       | Typ     | Standardwert | Beschreibung |
| :---------------- | :------ | :----------- | :----------- |
| controller_id     | string  |              | interne ID des Controllers, wird vom Konfigurator gefüllt |
| relay_id          | string  |              | interne ID der Zone, wird vom Konfigurator gefüllt |
| connector         | integer |              | Anschluss am Controller |
|                   |         |              | |
| with_daily_value  | boolean | true         | Tageswerte |
| with_workflow     | boolean | false        | Ablauf der Bewässerung (siehe Hydrawise.ZoneWorkflow) |
| with_status       | boolean | false        | Bewässerungsstatus (siehe Hydrawise.ZoneStatus) |
| with_flowrate     | integer | _average_    | Darstellung der aktuellen Wasser-Durchlaufmenge der Zone |
|                   |         |              | |
| visibility_script | integer | 0            | Script um die Sichtbarkeit von Variablen zu steuern |

Erläuterung zu _visibility_script_: diese optionale Script ermöglicht es dem Anwender, Variablen in Abhängigkeit von Variable auszublenden (z.B. keine Rest-Bewässerungsdauer, wenn keine Bewässerung aktiv ist). Ein Muster eines solchen Scriptes ist _libs/HydrawiseVisibility.php_.
Erläuterung zu _with_flowrate_: es gibt zwei Möglichkeiten, den Durchlauf ermitteln zu lassen
- _average_: Wert bezogen auf den abgelaufenen Anteil des Bewässerungszyklus, der Wert ist am Ende des Zyklus der Wert des gesamten Zyklus
- _current_: Wert bezogen auf das Zeitintervall seit der letzten Abfrage von Hydrawise; das entspricht eher dem aktuelle Durchfluß, schwankt aber mehr.

#### Statusvariablen

_LastRun_, _NextRun_: letzter bzw. nächster geplanten Zyklus.

_Daily*_: Tageswerte

#### editerbare Statusvariablen

_ZoneAction_: Stoppen und Starten eines Bewässerungzyklus (siehe Variablenprofil _Hydrawise.ZoneAction_).

_SuspendAction_: Bewässerung aussetzen bzw. eine Rücknahme der Aussetzung (siehe Variablenprofil _Hydrawise.SuspendAction_).

_SuspendUntil_: Ausgabe einer aktuellen Aussetzung der Bewässerung als auch die Möglichkeit, einen neuen End-Zeitpunkt anzugeben.

### Variablenprofile

Es werden folgende Variableprofile angelegt:
* Integer<br>
  - Hydrawise.ZoneAction: ist standrdmässig mit folgenden Ausprägungen angelegt: Stop, Voreinstellung, 1 min, ...<br>
Eine Anpassung an eigene Bedürfnisse ist möglich, der Wert der Assoziation ist die zu verwendende Bewässerungsdater in Minuten.
  - Hydrawise.ZoneSuspend:  ist standrdmässig mit folgenden Ausprägungen angelegt: Löschen, 1 Tag, ...<br>
Eine Anpassung an eigene Bedürfnisse ist möglich, der Wert der Assoziation ist die Dauer der Aussetzung der Bewässerung in Tagen
  - Hydrawise.Duration, Hydrawise.ProbabilityOfRain, Hydrawise.WaterSaving, Hydrawise.ZoneWorkflow, Hydrawise.ZoneStatus

* Float<br>
  - Hydrawise.Flowmeter, Hydrawise.WaterFlowrate, Hydrawise.Humidity, Hydrawise.Rainfall, Hydrawise.Temperatur, Hydrawise.WindSpeed

### Funktionen

`bool Hydrawise_Run(int $InstanzID, int $duration)`<br>
startet die Bewässerung dieser Zone für eine bestimmte Zeit (in Minuten). Ist _duration_ nicht angegeben bzw. _null_, wird die in Hydrawise für die Zone aktuell ermittelte Dauer verwendet.

`bool Hydrawise_Stop(int $InstanzID)`<br>
stoppt eine laufende Bewässerung.

`bool Hydrawise_Suspend(int $InstanzID, int $timestamp)`<br>
setzt die Bewässerung bis zum angegebenen Zeitpunkt aus.

`bool Hydrawise_Resume(int $InstanzID)`<br>
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
  - `{A717FCDD-287E-44BF-A1D2-E2489A4C30B2}`: an HydrawiseConfig, HydrawiseController, HydrawiseSensor, HydrawiseZone
  - `{B54B579C-3992-4C1D-B7A8-4A129A78ED03}`: an HydrawiseIO

## 7. Versions-Historie

- 1.19 @ 30.12.2019 10:56
  - Anpassungen an IPS 5.3
    - Formular-Elemente: 'label' in 'caption' geändert
  - Fix in CreateVarProfile()

- 1.18 @ 17.10.2019 07:55
  - Fehler abgefangen, wenn zu einem aktiven Bewässerungslauf keine Angabe der Wassermenge vorhanden ist
  - Anpassungen an IPS 5.2
    - IPS_SetVariableProfileValues(), IPS_SetVariableProfileDigits() nur bei INTEGER, FLOAT
    - Dokumentation-URL in module.json
  - Umstellung auf strict_types=1
  - Umstellung von StyleCI auf php-cs-fixer

- 1.17 @ 09.08.2019 14:32
  - Schreibfehler korrigiert

- 1.16 @ 05.08.2019 11:35
  - Fehler abfangen, wenn jemand keine gültigen Account hat

- 1.15 @ 31.07.2019 16:54
  - Redesign des Moduls
    - HydrawiseConfig legt nur noch einen HydrawiseController an
    - HydrawiseController legt HydrawiseSensor und HydrawiseZone an
	- der Datenabruf wird nicht zentral über HydrawiseIO getaktet sondern vom HydrawiseController
  - GUI an aktuelle Möglichkeiten angepasst, Einsatz des Konfigurators
  - Webhook für mehr als eine Controller mit getrenntem Namen einstellbar
  - Meldungen von Aktionen werden nun auch im Controller (für 60s) in 'letze Meldung' angezeigt 

- 1.14 @ 27.07.2019 18:13
  - Berechnung der aktuellen Wasser-Durchflussmenge pro Zone sowie Darstellung zu einer Wasseruhr

- 1.13 @ 25.07.2019 18:35
  - in HydrawiseController wird nun auch die Controller-ID angezeigt
  - Schreibfehler korrigiert

- 1.12 @ 20.07.2019 15:19
  - Handhabung von mehreren Controllern
  - Regensensor wird nun erkannt

- 1.11 @ 25.06.2019 18:25
  - Anpassung an IPS 5.1: Überarbeitung der Datenkommunikation<br>
    Achtung: die Zonen und Sensoren müssen (in der Instanz-Konfiguration) dem Gatewasy _HydrawiseIO_ zugeordnet werden; die Frage, ob der nicht mehr benötigte Gateway (Controller) gelöscht werden solle, ist mit **Nein** zu beantworten.

- 1.10 @ 23.04.2019 17:08
  - Konfigurator um Sicherheitsabfrage ergänzt

- 1.9 @ 29.03.2019 16:19
  - SetValue() abgesichert

- 1.8 @ 20.03.2019 14:08
  - form.json in GetConfigurationForm() abgebildet
  - Schalter, um die I/O-Instanz (temporär) zu deaktivieren
  - Anpassungen IPS 5

- 1.7 @ 26.01.2019 10:55
  - curl_errno() abfragen
  - es gibt ab und an Timeout-Fehler bei HTTP-Abruf, daher  wird optional erst nach dem X. Fehler reagiert
  - I/O-Fehler werden nicht mehr an die Instanzen weitergeleitet

- 1.6 @ 22.12.2018 11:35
  - Fehler in der http-Kommunikation nun nicht mehr mit _echo_ (also als **ERROR**) sondern mit _LogMessage_ als **NOTIFY**

- 1.5 @ 21.12.2018 13:10
  - Standard-Konstanten verwenden

- 1.4 @ 02.10.2018 10:47
  - Schreibfehler

- 1.3 @ 22.08.2018 17:42
  - Anpassungen IPS 5, Abspaltung Branch _ips_4.4_
  - Versionshistorie dazu
  - define's der Variablentypen
  - Schaltfläche mit Link zu README.md in den Konfigurationsdialogen
