core = 6.x
api = 2

; Test that revision passed in uses git to download project.
projects[context_admin][download][revision] = "eb9f05e"

; Test that make preserves VCS directories.
projects[context_admin][options][working-copy] = TRUE

; Test that make defaults to download type of git if any download
; parameters are present.
projects[cck_signup][download][revision] = "2fe932c"

; When branch is passed in addition to revision, .info file rewriting has better versioning.
projects[caption_filter][subdir] = "contrib"
projects[caption_filter][download][type] = "git"
projects[caption_filter][download][branch] = "7.x-1.x"
projects[caption_filter][download][revision] = "c9794cf"
projects[caption_filter][options][working-copy] = TRUE
