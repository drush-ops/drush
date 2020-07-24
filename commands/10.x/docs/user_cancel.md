# user:cancel

Cancel user account(s) with the specified name(s).

#### Examples

- <code>drush user:cancel username</code>. Cancel the user account with the name username and anonymize all content created by that user.
- <code>drush user:cancel --delete-content username</code>. Delete the user account with the name username and delete all content created by that user.

#### Arguments

- **names**. A comma delimited list of user names.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --delete-content**. Delete the user, and all content created by the user

#### Aliases

- ucan
- user-cancel

