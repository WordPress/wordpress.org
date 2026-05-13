# Gandalf Contract Fixtures

These fixtures define the WordPress.org Plugin Directory side of the Gandalf
scan HTTP contract.

Gandalf consumes these examples from a WordPress.org checkout in its private
compatibility agent test. WordPress.org is the source of truth for the public
request, accepted response, and callback shapes.

These fixtures are intentionally JSON-only so compatibility checks can read them
without bootstrapping WordPress.org.
