import datetime
from datetime import timedelta
from dateutil import parser
#	Sunrise, sunset
from astral import LocationInfo
from astral.sun import sun
import csv
import pytz
import os
import os.path
import requests
import json
import matplotlib as mpl
mpl.use('Agg')
import matplotlib.pyplot as plt
import matplotlib.dates as mdates
import numpy as np
import tweepy

#   IP Address of the DataManager
#	CHANGE THIS!
IP_address = "192.168.0.123"

#	Payment rate - £/kWh
payment_rate = 0.054

#   Sun information
city_name = 'London'
city = LocationInfo("city_name")
sun = sun(city.observer)
tz_info = (sun['sunrise']).tzinfo

#   Time information
now   = datetime.datetime.now()
zone  = pytz.timezone("Europe/London")
now   = zone.localize(now)
today = now.strftime("%Y-%m-%d")

# Set Path
path = os.path.dirname(os.path.abspath(__file__))

# Set the filename to be the human-readable timestamp
filename =  os.path.join(path, today)
csv_file = filename + ".csv"
png_file = filename + ".png"

#   During daylight
if datetime.datetime.now(tz_info) > (sun['sunrise']) and datetime.datetime.now(tz_info) < (sun['sunset']) :
    # Get the Watt value from the Inverter
    API_url = 'http://'+IP_address+'/solar_api/v1/GetInverterRealtimeData.cgi?Scope=System'
    response = requests.get(url=API_url)
    data = json.loads(response.text)
    watts = data['Body']['Data']['PAC']['Values']['1']
    time = now.isoformat()

    with open(csv_file, 'a', newline='') as csvfile:
        writer = csv.writer(csvfile)
        writer = writer.writerow([time,watts])

#       After sunset
if datetime.datetime.now(tz_info) > (sun['sunset']) :
    if not os.path.exists(png_file):
        #   Read the data
        data = np.genfromtxt(csv_file,
            unpack=True,
            names=['timestamp','watts','total'],
            dtype=None,
            delimiter = ',',
            converters={0: lambda x: parser.parse(x)})

        x = data['timestamp']
        y = data['watts']

        #	Calculate the total kWh generated.
        #	Sum the readings and divide by 60. (Because we read every minute & there are 60 minutes in an hour).
        #	Divide by 1,000 to get kWh
        #	Only need 2 decimal places of precision

        kWh = round( (sum(y) / 60 / 1000), 2)

        money = '£{:.2f}'.format( (round( (kWh * payment_rate) , 2) ) )

        #   Start the graph
        fig, ax = plt.subplots(figsize=(16,9))

        #   Colour Masks
        mask0 = y <   500
        mask1 = y >=  500
        mask2 = y >= 1000
        mask3 = y >= 1500
        mask4 = y >= 2000
        mask5 = y >= 2500
        mask6 = y >= 3000
        mask7 = y >= 3500
        mask8 = y >= 4000

        plt.bar(x[mask0], y[mask0], width=.002, color = '#F0FF00')
        plt.bar(x[mask1], y[mask1], width=.002, color = '#F1DF00')
        plt.bar(x[mask2], y[mask2], width=.002, color = '#F3BF00')
        plt.bar(x[mask3], y[mask3], width=.002, color = '#F59F00')
        plt.bar(x[mask4], y[mask4], width=.002, color = '#F77F00')
        plt.bar(x[mask5], y[mask5], width=.002, color = '#F95F00')
        plt.bar(x[mask6], y[mask6], width=.002, color = '#FB3F00')
        plt.bar(x[mask7], y[mask7], width=.002, color = '#FD1F00')
        plt.bar(x[mask8], y[mask8], width=.002, color = '#ff0000')

        #   X Axis
        hours = mdates.HourLocator()
        hoursFmt = mdates.DateFormatter('%H:%M')
        ax.xaxis.set_major_locator(hours)
        ax.xaxis.set_major_formatter(hoursFmt)

        day = x[0]
        x_start = day.replace(hour=5,  minute=0, second=0) # 0500
        x_end   = day.replace(hour=22, minute=0, second=0) # 2200

        ax.set_xlim(x_start,x_end)

        #   Y Axis
        ax.set_ylim(100, 4500)

        #   Axis properties
        ax.grid(axis="y",color='#FFFFFF', linestyle='-', linewidth=0.5)

        # rotates and right aligns the x labels, and moves the bottom of the
        # axes up to make room for them
        fig.autofmt_xdate()

        #   Background Colour
        ax.set_facecolor("#1f77b4")

        #   Set the labels
        plt.xlabel(today)
        plt.ylabel('Generated Electricity (Watts)')
        plt.title('Solar Panels (' + city_name + ')\nGenerated ' + str(kWh) + 'kWh - Earned ' + money)

        #   Save the Image
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
        status = "Today I generated "+str(kWh)+"kWh"
        upload = twitter_api.media_upload(png_file)
        media_ids = [upload.media_id_string]
        twitter_api.update_status(status=status, media_ids=media_ids, lat='51.486', long='0.107')
