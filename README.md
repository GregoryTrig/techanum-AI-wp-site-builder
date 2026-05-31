# Prompt To Page Plugin

A WordPress plugin that allows administrators to create new pages through a conversation with an LLM (Large Language Model).

## Features

- Create pages from prompts using AI
- Support for OpenRouter LLM provider
- Secure API key handling with encryption
- Admin interface for configuration and chat
- REST API endpoint for programmatic access

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- cURL extension for making HTTP requests

## Installation

1. Upload the plugin files to the `/wp-content/plugins/prompt-to-page` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to "Prompt To Page" under Settings to configure your API key.

## Usage

### Configuration
1. Go to **Settings > Prompt To Page**
2. Select your LLM provider (currently only OpenRouter is supported)
3. Enter your API key from the chosen provider
4. Save changes

### Creating Pages
After configuration, you can create pages in two ways:

1. **Admin Settings Page**: Use the "Create Page from Prompt" section on the settings page
2. **Chat Interface**: Navigate to **Prompt To Page Chat** in the admin menu to use a chat interface for page creation

## API Key Setup

To use this plugin, you'll need an API key from an LLM provider:

1. Visit [OpenRouter](https://openrouter.ai/?ref=YOURID) (recommended)
2. Sign up for an account
3. Generate an API key
4. Enter the key in the plugin settings

## Security

The plugin uses WordPress security keys to encrypt your API key before storing it in the database. This ensures that even if someone gains access to your database, they won't be able to retrieve your raw API key.

## Development

This plugin is built with extensibility in mind. The architecture supports different LLM connector implementations by implementing the `LLM_Connector` interface.

## License

This project is licensed under the GPL v3 or later.