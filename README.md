# IPSymconHydrawise

[![IPS-Version](https://img.shields.io/badge/Symcon_Version-5.4+-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
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

 - IP-Symcon ab Version 5.4<br>
   Version 5.3 mit Branch _ips_5.3_ (nur noch Fehlerkorrekturen)
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
Da die neuen Hydrawise-API 1.4 einige Daten nicht mehr liefert, ruft die Instanz Daten aus dem lokalen Hydrawise-Controller ab - ist eine undokumentierte API, liefert aber wieder wichtige Informationen, u.a. zur Dauer der Suspendierung einer Zone und zum Wasserverbrauch.
Es entfallen mit der API grundsätzlich die Wettervorhersage, das aktuelle Wetter sowie die Angaben zur Wassereinsparung; andere Daten werden nun selbst ermittelt und können daher etwas von der Hydrawise-App abweichen.

## 5. Konfiguration

### HydrawiseIO

Hierüber findet die http-Kommunikation statt.

#### Properties

| Eigenschaft                  | Typ     | Standardwert | Beschreibung |
| :--------------------------- | :------ | :----------- | :----------- |
| Instanz ist deaktiviert      | boolean | false        | Instanz temporär deaktivieren |
|                              |         |              | |
| Hydrawise-Zugangsdaten       |         |              | Benutzername und Passwort von https://app.hydrawise.com/config/login |
| API-Key                      | string  |              | API-Key aus Hydrawiese-App (_Kontoinformationen_) |
|                              |         |              | |
| lokaler Hydrawise-Controller |         |              | Angaben zum lokalen Hydrawise-Controller |
| Hostname                     |  string |              | Hostname oder IP-Adresse des Controllers im lokalen Netz |
| Passwort                     |  string |              | lokales Passwort, sichtbar auf dem Controller-Display (_Settings_ -> _Config_) |

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
| with_last_message      | boolean | false           | eventuell Nachricht zu der letzten Kommunikation, verschwindet nach 60 Sekunden wieder |
| with_waterusage        | boolean | true            | Informationen zum Wasserverbrauch |
| with_daily_value       | boolean | true            | Ermittlung von Tageswerten für Gesamtbewässerungszeit |
| with_status_box        | boolean | false           | HTML-Box mit einer Zusammenfassung der altuellen Bewässerung |
|                        |         |                 | |
|                        |         |                 | |
| WaterMeterID           | integer | 0               | Variablen-ID des Wasseruhr-Zählers |
| WaterMeterFactor       | float   | 1.0000          | Umrechnungsfaktor in Liter (z.B. 1000 wenn die Variable in m3 angibt) |
|                        |         |                 | |
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
| ImportCategoryID       | integer | 0               | ID der Kategorie unterhalb der die Instanzen angelegt werden |
| Sensoren und Zonen     |         |                 | Konfigurator zur Anlage der Komponenten dieses Controllers |

Erläuterung zu _minutes2fail_: Das hier angebbare Minuten-Intervall dient zu Überprüfung der Kommunikation zwischen dem Controller und dem Hydrawise-Server.
Ist die Zeit überschritten, wird die Variable _Status_ des Controllers auf Fehler gesetzt.
Anmerkung: die Variable _Status_ wird auch auf Fehler gesetzt wenn das IO-Modul einen Fehler feststellt.

Erläuterung zu _WaterMeter_: in der offiziellen Hydrawise-API sind seit Version 1.4 keine ANgaben zum Wasserverbrauch bzw Durchfluß mehr enthalten.
In der inoffiziellen lokalen API, die ich optional ebenfalls nutze, gibt es eine Angabe, die ich als aktuellen Wasster-Durchfluß interpretiere, die aber bei manchen Zonen Phantasiewerte liefert.
Daher kann man zur Ermittlung dieser Werte auf eine externe Wasseruhr zugreifen, das ersetzt die Verwendung der internen Angaben; vom Prinzip her wären die internen Informationen im Hydrawise natürlich viel exakter, insbesondere bei kurzen Zyklen und/oder geringen Mengen wird es potentiell ungenauer.

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
Die Beschreibung der Struktur siehe _Hydrawise_GetRawData()_.

Beispiel in module.php sind _Build_StatusBox()_ und _ProcessHook_Status()_.

#### Statusvariablen

es werden einige Variable angelegt, zum Teil optional. Zur Erklärung:
- _DailyReference_, ist ein UNIX-Timestamp, der das Datum enthält, auf den sich die Tageswerte beziehen. Wird automatisch bei dem ersten Nachricht nach Mitternacht auf den aktuellen Tag gestellt.
Betrifft die Variablen, die mit _Daily_ beginnen.

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


### HydrawiseZone

Das sind die einzelnen Bewässerungskreise, hier sind alle Zonen-spezifischen Daten abgelegt.

#### Properties

| Eigenschaft       | Typ     | Standardwert | Beschreibung |
| :---------------- | :------ | :----------- | :----------- |
| controller_id     | string  |              | interne ID des Controllers, wird vom Konfigurator gefüllt |
| relay_id          | string  |              | interne ID der Zone, wird vom Konfigurator gefüllt |
| connector         | integer |              | Anschluss am Controller |
|                   |         |              | |
| with_workflow     | boolean | false        | Ablauf der Bewässerung (siehe Hydrawise.ZoneWorkflow) |
| with_status       | boolean | false        | Bewässerungsstatus (siehe Hydrawise.ZoneStatus) |
| with_flowrate     | integer | _average_    | Darstellung der aktuellen Wasser-Durchlaufmenge der Zone |
| with_daily_value  | boolean | true         | Tageswerte |
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
  - Hydrawise.Flowmeter, Hydrawise.WaterFlowrate

### Funktionen

`bool Hydrawise_Run(int $InstanzID, int $duration)`<br>
startet die Bewässerung dieser Zone für eine bestimmte Zeit (in Minuten). Ist _duration_ nicht angegeben bzw. _null_, wird die in Hydrawise für die Zone aktuell ermittelte Dauer verwendet.

`bool Hydrawise_Stop(int $InstanzID)`<br>
stoppt eine laufende Bewässerung.

`bool Hydrawise_Suspend(int $InstanzID, int $timestamp)`<br>
setzt die Bewässerung bis zum angegebenen Zeitpunkt aus.

`bool Hydrawise_Resume(int $InstanzID)`<br>
aktiviert wieder die normale Bewässerung.

`string Hydrawise_GetRawData(int $InstanzID)`<br>
liefert die aufbereiteten Daten des Controllers, z.B. um damit einen Status-Auѕgabe zu machen.

#### Datenstrukturen

| Variable          | Datenty        | Bedeutung |
| :-----------      | :------------- | :-------- |
| status            | string         | Status des Controllers |
| last_contact_ts   | UNIX-Timestamp | letzter Kontakt des Controller zur Cloud |
| name              | string         | Bezeichnung des Controllers |
|                   |                | |
| running_zones     | Objekt-Liste   | Liste der zur Zeit bewässerten Zonen |
| done_zones        | Objekt-Liste   | Liste der heute bereits bewässerten Zonen |
| today_zones       | Objekt-Liste   | Liste der heute noch zu bewässernden Zonen |
| future_zones      | Objekt-Liste   | Liste der geplanten Bewässerungen |


#### zur Zeit bewässerte Zone (running_zones)

| Variable          | Datenty        | Bedeutung |
| :-----------      | :------------- | :-------- |
| name              | string         | Bezeichnung der Zone |
| duration          | integer        | verbleibende Dauer in Sekunden |
| waterflow         | integer        | altueller Wasserverbrauch in l/min |


#### bereits bewässerte Zone (done_zones)

| Variable          | Datenty        | Bedeutung |
| :-----------      | :------------- | :-------- |
| name              | string         | Bezeichnung der Zone |
| timestamp         | UNIX-Timestamp | Zeitpunkt des letzten Laufs |
| duration          | integer        | Dauer des letzten Laufs in Sekunden |
| is_running        | boolean        | wird zur Zeit bewässert (bei mehreren Zyklen pro Tag) |
| daily_duration    | integer        | Dauer aller Zyklen des Tages in Sekunden |
| daily_waterusage  | integer        | Wasserverbrauch aller Zyklen des Tages in Liter |


#### heute noch zu bewässernde Zone (today_zones)

| Variable          | Datenty        | Bedeutung |
| :-----------      | :------------- | :-------- |
| name              | string         | Bezeichnung des Controllers |
| timestamp         | UNIX-Timestamp | Zeitpunkt des nächsten Laufs |
| duration          | integer        | Dauer des nächsten Laufs in Sekunden |


#### geplante Bewässerungen (future_zones)

| Variable          | Datenty        | Bedeutung |
| :-----------      | :------------- | :-------- |
| name              | string         | Bezeichnung des Controllers |
| timestamp         | UNIX-Timestamp | Zeitpunkt des nächsten Laufs |
| duration          | integer        | Dauer des nächsten Laufs in Sekunden |


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
  - `{B54B579C-3992-4C1D-B7A8-4A129A78ED03}`: an HydrawiseIO
  - `{A800ED12-C177-80A3-A15C-0B6E0052640D}`: an HydrawiseController
  - `{C424E279-1362-96A6-7D22-B879926BF95F}`: an HydrawiseZone
  - `{D957666E-B6E3-A44F-2515-9B5F009ACC2D}`: an HydrawiseSensor, HydrawiseZone
  - `{A717FCDD-287E-44BF-A1D2-E2489A4C30B2}`: an HydrawiseController, HydrawiseSensor, HydrawiseZone

## 7. Versions-Historie

- 1.25 @ 14.07.2021 18:37
  - PHP_CS_FIXER_IGNORE_ENV=1 in github/workflows/style.yml eingefügt
  - Bugfix in der IO-Instanz, TestAccount(): undefinierte Variable 'txt'
  - Konstatenten-Definition fehlerhaft (doppelter Wert)

- 1.24 @ 12.09.2020 12:14
  - LICENSE.md hinzugefügt
  - Properties der Basiskonfiguration sind nicht mehr editierbar
  - Nutzung von HasActiveParent(): Anzeige im Konfigurationsformular sowie entsprechende Absicherung von SendDataToParent()
  - interne Funktionen sind nun "private"
  - library.php in local.php umbenannt
  - lokale Funktionen aus common.php in locale.php verlagert
  - Traits des Moduls haben nun Postfix "Lib"
  - define's durch statische Klassen-Variablen ersetzt

- 1.23 @ 24.06.2020 12:47
  - Anpassungen an IPS 5.4<br>
    - Umstellung der Variablenabfrage von HydrawiseZone/HydrawiseController-Instanzen auf Nachrichtenfluss<br>
      SendDataToChildren() liefert nun ein Array der Werte der ReceivsData-Rückgabewerte

- 1.22 @ 17.06.2020 21:40
  - Fix: falscher Timer

- 1.21 @ 03.05.2020 16:29
  - Anpassung an die neue API-Version 1.4<br>
    die API liefert keine Information mehr über:
	- über den Wasserverbrauch eines Bewässerungszklus
	- die letzte Bewässerung (Zeitpunkt und Dauer), wird nun selbst ermittelt
	- den Endzeitpunkt der Suspendierung einer Zone
	- Suspendierung einer Zone mit einer Dauer kürzer als (vermutlich) einer Woche wird nicht als Suspendierung gemeldet
	- die wöchentliche Bewässerungszeit und Wassereinsparung
	- das aktuelles Wetter und Vorhersage
  - optionaler Abruf von Daten aus dem lokalen Hydrawise-Controller um Lücken in der Hydrawise-API zu füllen
    - Ermittlung des Wasserverbrauchs und der Durchflussrate
    - Endzeitpunkt der Suspendierung einer Zone
  - optionale Auswertung eines externen Wasserzählers zur Berechnung von Wasserverbrauch und Durchfluß<br>
    Hintergrund: die Angabe in der Datenabrufen sind leider ziemlich unzuverlässig

- 1.20 @ 01.01.2020 15:47
  - Fix bei Vorhersage
    - keine Erkennung, wieviel Tage gewünscht sind (ganz oder gar nicht)
	- fehlerhafte Verarbeitung der Daten
  - Schreibfehler korrigiert

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
