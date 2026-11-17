# OSS Scanner

These files let [Anthropic's OSS Scanner](https://red.anthropic.com/oss-scanner) build this repository and look for security vulnerabilities in it. Its reports go to the WordPress Security Team.

- `Dockerfile` builds the image the scanner works in: every environment in [`environments/`](../environments), with the network available. The scan itself runs without it.
- `bin/wporg-provision` builds the sites the way `wp-env start` would, on one Apache and one MariaDB instead of in Docker containers. `bin/wp-env` stands in for `wp-env`, so the npm scripts and lifecycle scripts in `environments/` work unchanged.
- `bin/wporg` starts, lists and checks the sites.
- `threat_model.md`, `report_guidelines.md` and `patch_guidelines.md` tell the scanner what matters, how to rate it, and how reports and patches should look.

A new environment in `environments/` is picked up without changes here. A [workflow](../.github/workflows/oss-scanner.yml) builds the image and runs every site and test suite in it offline, daily and when any of this changes.

## Trying it locally

From a clean checkout, without `vendor/` or `environments/node_modules/`, which would be copied into the image:

```bash
docker build --file .oss-scanner/Dockerfile --tag wporg-oss-scanner .
docker run --rm -it --network none wporg-oss-scanner bash
```

If your hosts file points WordPress.org at a sandbox, the build's downloads from it fail with a 403. Pass `--add-host=wordpress.org:<public IP>` and the like for those hosts.

Then, in the container:

```bash
wporg start
wporg sites
wporg check --tests
```
