# Changelog

## 2.1.0

* Compatibility: Requires WPiko Chatbot 2.1.0 or newer. Update both plugins together.
* Security: Updated contact-form buttons to use delegated event listeners and work with the base plugin's safer message rendering. Thanks to Ali Hidayat for responsibly reporting the stored XSS vulnerability in assistant messages.
* Security: Added signature verification for license-server requests that revoke licenses or change their expiration dates.
* Feature: Added controlled WooCommerce order lookup with rate limits. Guests must provide an order number and matching checkout email to receive basic status; signed-in order owners can receive the additional fields enabled by the store.
* Privacy: Replaced order-file synchronization with controlled order lookup and added background cleanup of old order exports from OpenAI.
* Improve: Track pages covered by Pro website scans and update coverage when knowledge files are removed, so the base plugin's page-learning tools can recognize existing training.
* Performance: Load Pro frontend scripts and styles only on pages where the chatbot is displayed.

## 2.0.8

* Feature: Added GPT-6 Sol and GPT-6 Luna to AI Configuration and the OpenAI Responses API model list.
* Improve: Added their supported reasoning efforts, file search support, and model details including context size and token pricing.
* Update: Switched conversation translation and contact text enhancement to GPT-6 Luna, and website Q&A generation to GPT-6 Sol.
* Improve: Added Expand all and Collapse all controls to the admin sidebar, allowed multiple navigation groups to stay open, and remembered open groups between pages.
