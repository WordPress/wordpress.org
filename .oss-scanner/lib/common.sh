# Shared by the scripts in ../bin. Sourced, not run.

ENVDIR=/src/environments
SITES=/srv/sites
WPORG_OPT=/opt/wporg
LOGS=/var/log/wporg
RUNDIR=/run/wporg

# The wp-env database credentials, which the lifecycle scripts and tests assume.
DB_USER=root
DB_PASSWORD=password

# The hosts environments/freescout/docker-compose.yml gives FreeScout's services, all on this machine here.
HOST_ALIASES='db greenmail mailpit mock-api'

die() {
	printf '%s: %s\n' "$( basename "$0" )" "$*" >&2
	exit 1
}

warn() {
	printf '%s: warning: %s\n' "$( basename "$0" )" "$*" >&2
}

# Prints the site name for a wp-env config: its directory, with -test for .wp-env.test.json.
site_name() {
	local config
	config=$( realpath -m "$1" )
	case $config in
		"$ENVDIR"/*/.wp-env.json) basename "$( dirname "$config" )" ;;
		"$ENVDIR"/*/.wp-env.test.json) echo "$( basename "$( dirname "$config" )" )-test" ;;
		*) die "$1 is not an environment config under $ENVDIR" ;;
	esac
}

# Loads a site's settings (NAME, KIND, CONFIG, PORT, ROOT, TESTS, DB) into the caller's scope.
load_site() {
	[ -f "$SITES/$1/site.env" ] || die "no site named $1 (see: wporg sites)"
	# shellcheck source=/dev/null
	. "$SITES/$1/site.env"
}
