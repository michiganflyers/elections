#!/bin/bash
read -p "Skymanager Username: " username
read -sp "Skymanager Password: " password; echo

token="$(curl -vvv 'https://umflyers.skymanager.com/Home/LogIn?ReturnUrl=%2f' -X POST -H 'Content-Type: application/x-www-form-urlencoded' --data-urlencode  "Username=$username" --data-urlencode "Password=$password" --data-urlencode "RememberMe=false" 2>&1 | grep -o 'Set-Cookie: .ASPXAUTH[^;]*' | cut -f2- -d' ')";

. process.sh

help() {
	cat <<- _END_
		userlist help:

		  -h    Shows this help menu
		  -r    Reprocess existing fetched data
	_END_
}

reprocess=false
while getopts "hr" opt; do
	case "$opt" in
		r) reprocess=true ;;
		h) help
		   exit ;;
	esac
done

if ! $reprocess; then
	mkdir -p tmp
	pushd tmp
	for letter in {A..Z}; do 
		printf "%s\n" $letter >&2;
		curl -s "https://umflyers.skymanager.com/Roster/Letter/$letter" -H "Cookie: $token" \
			| tee "raw-$letter.html" | process
	done | tee ../results.json > /dev/null

	popd
fi

echo -n "Eligible voters: "
jq -c '. | select(.balance >= 0) | select(.tags | contains(["Flying"]) or contains(["Honorary Dues"]) or contains(["CFI/Mechanic"]) or contains(["CFI/MECH/DIRECTORS Dues"]))' results.json | tee voters.json | wc -l
