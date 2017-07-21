#!/bin/sh
dir=$(dirname $1)
file=$(basename $1)
mkdir -p $dir/jsxgen
babel --presets=react,es2015 $1x > $dir/jsxgen/$file
