# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A PHP script that fetches all favorited articles from a Hacker News user's favorites page and redirects to a random one.

## Usage

The script is accessed via HTTP with optional GET parameters:
- `id` - HN username (defaults to 'arnok')
- `debug` - Enable debug mode to see all fetched articles instead of redirecting

Example: `rand-hn-fav.php?id=username&debug`

## Architecture

Single-file PHP application (`rand-hn-fav.php`) that:
1. Paginates through all HN favorites pages for a user
2. Parses HTML using DOMDocument/XPath to extract article URLs from `span.titleline` elements
3. Follows "More" pagination links until all favorites are collected
4. Redirects to a randomly selected article (or displays debug info)

## Running Locally

Requires PHP with DOM extension. Run via any PHP-capable web server or:
```
php -S localhost:8000
```
Then access `http://localhost:8000/rand-hn-fav.php`
