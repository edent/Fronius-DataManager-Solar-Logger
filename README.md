Fronius-DataManager-Solar-Logger
================================

Log the performance of your solar panels with Fronius's DataManager.

This will create a .csv file with a timestamp, the real-time power being generated (Watts), and total energy generated that day (kWh).

The script only calls the API after sunrise and before sunset at your Latitude & Longitude.

You can place this in your crontab to run every minute - or as often as you wish.
