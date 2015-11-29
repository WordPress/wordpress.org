#!/bin/sh

# Based on https://gist.github.com/esperlu/943776

mysqldump  --compatible=ansi --skip-extended-insert --compact  "$@" | \

awk '
BEGIN {
	FS=",$"
	print "PRAGMA synchronous = OFF;"
	print "PRAGMA journal_mode = MEMORY;"
	print "BEGIN TRANSACTION;"
}

# Print all `INSERT` lines. The single quotes are protected by another single quote.
/INSERT/ {
	gsub( /\\\047/, "\047\047" )
	gsub(/\\n/, "\n")
	gsub(/\\r/, "\r")
	gsub(/\\"/, "\"")
	gsub(/\\\\/, "\\")
	gsub(/\\\032/, "\032")
	print
	next
}

# Print all `KEY` creation lines.
END {
	print "END TRANSACTION;"
}
'
exit 0
