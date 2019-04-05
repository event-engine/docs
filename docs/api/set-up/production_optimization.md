# Optimize For Production

{.alert .alert-important}
This page is not migrated yet! [Learn more](https://event-engine.io/news/2019-04-05.html#4-1-3){: class="alert-link"}.

{.alert .alert-warning}
Work in Progress

The `Description phase` configures Event Engine. Depending on the size of the application this can result in many method calls
which are known to be slow. During development that's not a problem but in production you don't want to do that on every request.
Between two deployments code does not change and therefor the configuration does not change. We can safely cache it and respond faster to requests.