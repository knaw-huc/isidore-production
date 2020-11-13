#!/bin/bash

find . -name "*.jpg" -type f | while read fname 

do      
               mogrify -resize 170x "$fname"
               echo "$fname resized"
done