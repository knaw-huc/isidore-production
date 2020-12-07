#!/bin/bash

find . -name "*.jpg" -type f | while read fname 

do      
               mogrify -resize 400x "$fname"
               echo "$fname resized"
done