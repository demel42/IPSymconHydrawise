# NetatmoWeather

Modul für IP-Symcon ab Version 4.

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)  
2. [Voraussetzungen](#2-voraussetzungen)  
3. [Installation](#3-installation)  
4. [Funktionsreferenz](#4-funktionsreferenz)
5. [Konfiguration](#5-konfiguration)  
6. [Anhang](#6-anhang)  

## 1. Funktionsumfang

## 2. Voraussetzungen

 - IPS 4.x
 - Hunter/Hydrawise 

## 3. Installation

### a. Laden des Moduls

Die IP-Symcon (min Ver. 4.x) Konsole öffnen. Im Objektbaum unter Kerninstanzen die Instanz __*Modules*__ durch einen doppelten Mausklick öffnen.

In der _Modules_ Instanz rechts oben auf den Button __*Hinzufügen*__ drücken.
 
In dem sich öffnenden Fenster folgende URL hinzufügen:

`https://github.com/demel42/IPSymconHydrawise.git`
    
und mit _OK_ bestätigen.    
        
Anschließend erscheint ein Eintrag für das Modul in der Liste der Instanz _Modules_    

### b. Einrichtung in IPS

In IP-Symcon nun unterhalb von _I/O Instanzen_ die Funktion _Instanz hinzufügen_ (_CTRL+1_) auswählen, als Hersteller _XXXXXXX_ und als Gerät _XXXXXXX_ auswählen.

In dem Konfigurationsdialog die Hydrawise-Zugangsdaten eintragen.

Dann unter _Konfigurator Instanzen_ analog den Konfigurator _XXXXXXXXXXXXXX_ hinzufügen.

## 4. Funktionsreferenz

### zentrale Funktion

`UpdateData(int $InstanzID)`

ruft die Daten von Hydrawise ab Wird automatisch zyklisch durch die Instanz durchgeführt im Abstand wie in der Konfiguration angegeben.

## 5. Konfiguration

### I/O-Modul

#### Variablen

| Eigenschaft               | Typ      | Standardwert | Beschreibung |
| :-----------------------: | :-----:  | :----------: | :----------------------------------------------------------------------------------------------------------: |
| Hydrawise-Zugangsdaten    | string   |              | Benutzername und Passwort von https://app.hydrawise.com/config/login                                         |
|                           |          |              | |
| UpdateDataInterval        | integer  | 30           | Angabe in Minuten                          |

#### Schaltflächen

| Bezeichnung                  | Beschreibung |
| :--------------------------: | :------------------------------------------------: |
| Aktualisiere Daten           | führt eine sofortige Aktualisierung durch |

### Konfigurator

#### Auswahl

Es werden alle Controller zu dem konfigurierten Account zur Auswahl angeboten. Es muss allerdings nur eine Auswahl getroffen werden, wenn es mehr als ein Controller gibt.

#### Schaltflächen

| Bezeichnung                  | Beschreibung |
| :--------------------------: | :------------------------------------------------: |
| Import des Controller        | richtet die Geräte-Instanzen ein |
  
### Geräte

#### Properties

werden vom Konfigurator beim Anlegen der Instanz gesetzt.

| Eigenschaft            | Typ     | Standardwert | Beschreibung                               |
| :--------------------: | :-----: | :----------: | :----------------------------------------: |
|                        | string  |              |                                            |

_module_type_: _NAMain_=Basis, _NAModule1_=Außen, _NAModule2_=Wind, _NAModule3_=Regen, _NAModule4_=Innen sowie _Station_, die für die Netatmo-Station als Ganzes steht.

#### Variablen

stehen je nach Typ des Moduls zur Verfügung

| Eigenschaft               | Typ     | Standardwert | Beschreibung                               |
| :-----------------------: | :-----: | :----------: | :----------------------------------------: |
|                           |         |              |                                            |


Erläuterung zu _statusbox_script_, _webhook_script_:
mit diesen Scripten kann man eine alternative Darstellung realisieren.

Ein passendes Code-Fragment für ein Script:

```
$data = HydrawiseController_GetRawData($_IPS['InstanceID']);
if ($data) {
	$controller = json_decode($r,true);
	...
	echo $result;
}
```
Die Beschreibung der Struktur siehe _HydrawiseController_GetRawData()_.

Beispiel in module.php sind _Build_StatusBox()_ und _ProcessHook_Status()_.

### Statusvariablen

folgende Variable werden angelegt, zum Teil optional

| Name             | Typ     | Beschreibung                                    | Option                 |
| :--------------: | :-----: | :---------------------------------------------: | :--------------------: |
|                  |         |                                                 |                        |

### Variablenprofile

Es werden folgende Variableprofile angelegt:
* Boolean<br>

* Integer<br>

* Float<br>

* String<br>

## 6. Anhang

GUIDs
- Modul: `{CCB22FB7-9262-4387-98E4-256A07E37816}` 

- HydrawiseIO: `{5927E05C-82D0-4D78-B8E0-A973470A9CD3}`
- HydrawiseConfig: `{92DEBBAA-3191-4FA8-AB36-ED82BEA08154}`
- HydrawiseController: `{B1B47A68-CE20-4887-B00C-E6412DAD2CFB}`
- HydrawiseZone: `{6A0DAE44-B86A-4D50-A76F-532365FD88AE}`
- HydrawiseSensor: `{56D9EFA4-8840-4DAE-A6D2-ECE8DC862874}`
