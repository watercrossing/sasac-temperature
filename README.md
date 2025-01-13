# St Albans Sub Aqua Club Temperature Measurements

There is an ESP32 device running Tasmota with two DS18B20 temperature sensors. One is integrated into the pool pump loop measuring the water temperature, the other one measures the air temperature. The tasmota device pushes the temperature readings to the sasac.co.uk servers every five minutes, using the following logic:
```
TempRes 2
Rule1 ON DS18B20-1#Temperature DO Var1 %value% ENDON ON DS18B20-2#Temperature DO Var2 %value% ENDON
Rule2 ON Time#Minute|5 DO WebQuery URL/tempupdate.php?key=SECRET&water=%Var1%&air=%Var2% ENDON
Rule1 1
Rule2 1
```

The `tempupdate.php` script receives the updates, validates them, and adds them to the databse. `temperature-logger.php` is a wordpress plug-in that creates [the information available on the sasac website](https://sasac.co.uk/water-temperature/), and provides a [rest API](https://sasac.co.uk/wp-json/temperature/v1/current) to access the data.
