## With thanks to https://github.com/grann0s/_pushPVStringData.php
## And https://forum.pvoutput.org/t/fronius-mppt-1-and-2-voltages-and-details/1259/
## This code MIT

import datetime
from datetime import timedelta
import pytz
import os
import os.path
import requests
import json
import matplotlib as mpl
mpl.use('Agg')
import matplotlib.pyplot as plt
import tweepy
import csv

#   IP Address of the DataManager
#	CHANGE THIS!
fronius_IP_address = "192.168.0.123"

#   Time information
now   = datetime.datetime.now()
zone  = pytz.timezone("Europe/London")
now   = zone.localize(now)
today = now.strftime("%Y-%m-%d")

# Set Path
path = os.path.dirname(os.path.abspath(__file__))

# Set the filename to be the human-readable timestamp
filename =  os.path.join(path, today) + "-strings"
png_file = filename + ".png"
csv_file = filename + ".csv"

# Get todays data from the inverter
API_url = "http://" + fronius_IP_address + "/solar_api/v1/GetArchiveData.cgi?Scope=System&StartDate=" + today + "&EndDate=" + today + "&Channel=Voltage_DC_String_1&Channel=Current_DC_String_1&Channel=Voltage_DC_String_2&Channel=Current_DC_String_2"

response = requests.get(url=API_url)
data = json.loads(response.text)

string1_current = data["Body"]["Data"]["inverter/1"]["Data"]["Current_DC_String_1"]["Values"]
#	Keys to ints, Values to floats
string1_current = {int(k):float(v) for k,v in string1_current.items()}
string1_current = sorted(string1_current.items())

string2_current = data["Body"]["Data"]["inverter/1"]["Data"]["Current_DC_String_2"]["Values"]
#	Keys to ints, Values to floats
string2_current = {int(k):float(v) for k,v in string2_current.items()}
string2_current = sorted(string2_current.items())

string1_voltage = data["Body"]["Data"]["inverter/1"]["Data"]["Voltage_DC_String_1"]["Values"]
#	Keys to ints, Values to floats
string1_voltage = {int(k):float(v) for k,v in string1_voltage.items()}
string1_voltage = sorted(string1_voltage.items())

string2_voltage = data["Body"]["Data"]["inverter/1"]["Data"]["Voltage_DC_String_2"]["Values"]
#	Keys to ints, Values to floats
string2_voltage = {int(k):float(v) for k,v in string2_voltage.items()}
string2_voltage = sorted(string2_voltage.items())

timestamp_list = []
string1_watts  = []
string2_watts  = []

for current, voltage in zip(string1_current, string1_voltage):
	timestamp_list.append(str(datetime.timedelta(seconds=current[0]))[:-3])	#	Remove the seconds
	string1_watts.append(int(current[1] * voltage[1]))

for current, voltage in zip(string2_current, string2_voltage):
	string2_watts.append(int(current[1] * voltage[1]))

#	Remove the first 4:30 hours (54 * 5 minutes)
#	Earliest sunrise about 04:40
#	Latest sunset about 2130
timestamp_list = timestamp_list[54:]
string1_watts  = string1_watts[54:]
string2_watts  = string2_watts[54:]

#	Total Power Generation
#	Bit sketchy. Only samples ever 5 minutes.
string1_kWh = str(round(sum(string1_watts) * 5 / 60 / 1000, 2))
string2_kWh = str(round(sum(string2_watts) * 5 / 60 / 1000, 2))

with open(csv_file, 'w', newline='') as csvfile:
	writer = csv.writer(csvfile)
	for a, b in zip(zip(timestamp_list,string1_watts),string2_watts):
		t  = str(a[0])
		w1 = str(a[1])
		w2 = str(b)
		writer.writerow([t,w1,w2])

# #   Start the graph
#	Values in INCHES!
fig, ax = plt.subplots(figsize=(8,4.5))

# Plot the two graphs
ax.plot(timestamp_list, string1_watts, label='West Panels ('+string1_kWh+'kWh)', linewidth=3)
ax.plot(timestamp_list, string2_watts, label='East Panels ('+string2_kWh+'kWh)', linewidth=3)

#   Set the labels
ax.set_xlabel(today)
ax.set_ylabel('Generated Electricity (Watts) Per String')
ax.set_title('Solar Panels (London)')

#	Set Legend
legend = ax.legend(loc='upper right', shadow=True)

#	Format the X Labels
ax.set_xticks(ax.get_xticks()[::12])	#	5 minute samples, so every 1 hour
fig.autofmt_xdate()	#	Space them out

#   Y Axis
ax.set_ylim(0)

#	Add a grid
ax.grid()

#	Save the image
plt.savefig(png_file, bbox_inches='tight')

twitterConsumerKey    = ""
twitterConsumerSecret = ""
twitterToken          = ""
twitterTokenSecret    = ""
# OAuth process, using the keys and tokens
auth = tweepy.OAuthHandler(twitterConsumerKey, twitterConsumerSecret)
auth.set_access_token(twitterToken, twitterTokenSecret)

# Creation of the actual interface, using authentication
twitter_api = tweepy.API(auth)
status = "East facing solar panels 🆚 West facing solar panels. London, UK."
upload = twitter_api.media_upload(png_file)
media_ids = [upload.media_id_string]
##	Not available in all versions of Tweepy
# twitter_api.create_media_metadata(media_ids, "East generated " + string1_kWh + "kWh - and West generated " + string2_kWh + "kWh")
twitter_api.update_status(status=status, media_ids=media_ids, lat='51.486', long='0.107')
