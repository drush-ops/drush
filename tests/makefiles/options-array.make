core = 6.x
api = 2

; Test that make preserves VCS directories.
options[working-copy] = TRUE
; Test that make does not require a Drupal core project.
options[no-core] = TRUE

; Test that make defaults to download type of git if any download
; parameters are present.
projects[cck_signup][download][revision] = "2fe932c"

; Test that make defaults to download type of git if any download
; parameters are present.
projects[context_admin][download][revision] = "eb9f05e"
