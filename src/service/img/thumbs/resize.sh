#!/bin/bash

find . -name "*.jpg" -type f | while read fname 

do

        
               mogrify -resize 75x "$fname"
               echo "$fname resized"
done