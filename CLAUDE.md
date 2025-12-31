# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A PHP script that fetches all favorited articles from a Hacker News user's favorites page and redirects to a random one.

## Usage

The script is accessed via HTTP with GET parameters:
- `id` - HN username (defaults to 'arnok', 2-15 chars, alphanumeric/dash/underscore)
- `target` - Redirect destination: `article` or `comments` (defaults to 'comments')
- `debug` - Enable debug mode to see algorithm steps instead of redirecting

Example: `rand-hn-fav.php?id=username&target=article&debug`

## Architecture

Single-file PHP application (`rand-hn-fav.php`) that uses binary search for efficiency:
1. Fetches page 1 to get items-per-page count
2. Exponential search (p=2,4,8,16...) to find upper bound
3. Binary search to find the actual last page
4. Calculates total articles and selects random index
5. Fetches only the page containing the selected article
6. Redirects to article URL or HN comments page

HTML responses are cached during execution to avoid duplicate requests.

## Running Locally

Requires PHP with DOM extension. Run via any PHP-capable web server or:
```
php -S localhost:8000
```
Then access `http://localhost:8000/rand-hn-fav.php`
