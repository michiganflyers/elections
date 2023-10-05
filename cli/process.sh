#!/bin/bash

process() {
	tr -dc '\n\040-\176' \
		| grep -oe '<div><b>[^<]*' -oe '<div>Email <a href="mailto:[^"]*' -oe '<span class="tag">[^<]*' -oe '<li><a href="/Roster/Member/[0-9]*' -oe '<div>Balance \$.*</div>' | tee stage-a.dat \
		| sed 's/^\s*//;s%^<div><b>\([^(]*\) (\([^)]*\)).*$%{"name": "\1", "username": "\2", "tags": [%;s%^<div><b>\([^<]*\).*$%{"username": "\1", "tags": [%;s%^<span class="tag">\([^<]*\).*$%"\1",%;s%^<li><a href="/Roster/Member/\([0-9]*\).*$%], "smid": \1}%;s%<div>Email.*mailto:\([^"]*\).*$%"email": "\1",%;s%^<div>Balance \$\([0-9,.]\+\).*$%"balance": \1,%;s%^<div>Balance \$<span[^>]*>(\([0-9,.]\+\)).*$%"balance": -\1,%;s%\("balance": -\?[0-9]\+\),\([0-9,.]\+\), %\1\2, %;s%\("balance": -?[0-9]\+\),\([0-9.]\+\), %\1\2, %;s%^<div>Balance $&nbsp;-&nbsp;.*$%"balance": 0,%' | tee stage-b.dat \
		| tr -d '\n' | tee stage-c.dat \
		| sed 's%"tags": \[\("email": [^,]*,\)%\1 "tags": [%g;s%,\]%]%g;s%"tags": \[\("balance"[^],]*,\)%\1 "tags": [%g;s%"tags": \[\("balance"[^],]*\)\]%\1, "tags": []%g' | tee stage-d.dat \
		| jq -c . | tee stage-e.dat
}
