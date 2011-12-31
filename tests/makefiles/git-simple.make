core = 6.x
api = 2

; Test that make defaults to download type of git if any download
; parameters are present.
projects[cck_signup][download][revision] = "2fe932c"

; Test that revision passed in main level works as shorthand for download revision.
projects[context_admin][revision] = "eb9f05e"
