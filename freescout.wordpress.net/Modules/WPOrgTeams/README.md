# WP.org Teams

Grants mailbox access and user permissions through the Teams module's teams, so agents are users rather than administrators, and nobody's access is set one user at a time. Each member gets exactly what their teams have.

## How it works

- **A team's page** (Manage » Teams) gets a Permissions group under its mailboxes, with core's user permissions, those of modules like Workflows included. A team's mailboxes and permissions are what its members get.
- **Members' access is written to them.** Core reads mailbox access from `mailbox_user` and permissions from the user's `permissions` column, without a hook, so each member's rows are updated whenever a team's members, mailboxes, or permissions change, the team is deleted, or the member's role changes. A mailbox a member keeps keeps its settings, like hiding it.
- **Teams only.** Users get nothing beyond what their teams grant: someone in no team has no mailboxes. Permissions a team doesn't grant are left unset, so Manage » Settings' defaults for all users still apply. "Can see only assigned conversations" isn't a team permission and stays as it is. Administrators aren't changed: they have everything anyway.
- **A user's Teams page** replaces core's Permissions page, which redirects to it. Administrators, and members of a team with "Users are allowed to manage users", change a user's teams there; those allowed to manage users can't change administrators'. It also lists what the teams give.
- **Create a New User** asks for teams instead of mailboxes.
- **A mailbox's Permissions page** shows who has access but can't change it: saving it keeps the mailbox's teams' members. Its access settings, like who manages the mailbox, still work.

## The team form

The Teams module's form has no hook to add fields with. The module serves `teams::update` from its own `Resources/views/teams/`, which renders the Teams module's form and adds the Permissions group before the form group of its Save button (`Services/TeamForm.php`). Updates to the Teams module's form come through; if its Save button can't be found anymore, the form shows without the permissions, and the module logs it.

A hidden field tells the save that the permissions were on the form, so a form without them never clears a team's permissions.

## Switching over

Saving a team only updates its members. Once the teams are set up:

```bash
php artisan wporgteams:sync          # lists what would change for each user
php artisan wporgteams:sync --apply  # changes it
```

Users in no team lose their mailboxes, and the permissions only they had.

## Without the Teams module

Everything stays as core has it. The module requires Teams in `module.json`, but checks that it's on before doing anything.
