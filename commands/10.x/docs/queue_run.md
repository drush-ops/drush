# queue:run

Run a specific queue by name.

#### Arguments

- **name**. The name of the queue to run, as defined in either hook_queue_info or hook_cron_queue_info.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --time-limit=TIME-LIMIT**. The maximum number of seconds allowed to run the queue
- ** --items-limit[=ITEMS-LIMIT]**. The maximum number of items allowed to run the queue

#### Aliases

- queue-run

