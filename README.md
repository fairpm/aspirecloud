[//]: # (@formatter:off)
# AspireCloud

This project is designed to function as an API endpoint for distributing [FAIR](https://fair.pm) packages.  Currently it can serve WordPress plugins through [FAIR Connect](https://github.com/fairpm/fair-plugin) as well as extensions for TYPO3 v14.0 and up.

## Quick Start

If you're new to FAIR, we recommend using the [start-here repo](https://github.com/fairpm/start-here#quick-start), which will download and bootstrap an AspireCloud instance with just one command (`just start`).  After that, your instance will be accessible at https://api.aspirecloud.localhost.

### Quick Start for Standalone instance 

If you're not running the whole start-here stack, you can still bring up an AC instance with `just start` in a fresh checkout of the AspireCloud repo (this one).  Since none of the services expose local ports by default, you will want to edit `docker-compose.override.yml` to add listening ports (see docker-compose.override.yml.dist for examples).    

## Notes

AspireCloud operates as an API and a pseudo pull-through cache against WordPress.org. This means that if AspireCloud provides the requested endpoint, it attempts to deliver the resource; otherwise, it passes the request through to WordPress.org and returns their response to the end user.

The long-term goal is to gradually implement WordPress.org APIs to reduce reliance on their website and endpoints.

**Important**: Please do not use this project to flood or harass the WordPress.org website. We don't want to get banned from using their resources!

## License

This project is licensed under the [MIT License](https://opensource.org/license/mit). You may exercise all rights granted by the MIT license, including using this project for commercial purposes.
