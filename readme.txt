=== Contact Forms, Live Support, CRM, Video Messages ===
Contributors: videowhisper
Author: VideoWhisper.com
Author URI: https://videowhisper.com
Plugin Name: Contact Forms, Live Support, CRM
Plugin URI: https://videowhisper.com
Donate link: https://videowhisper.com/
Tags: support, chat, contact, form, CRM
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Requires at least: 5.1
Requires PHP: 7.4
Tested up to: 6.6
Stable tag: 1.11

Streamline support with integrated CRM, live chat, and custom contact forms for enhanced user interaction.


== Description ==

Revamp your customer service with this powerful tool that combines contact forms, support tickets, chat, and a comprehensive CRM system to enhance interaction and efficiency. Manage conversations front and back end, attach multimedia files, webcam recordings and customize contact forms for specific needs. A floating icon and reporting buttons on content facilitate communication, making it ideal for any business aiming to improve responsiveness and customer satisfaction.

= Benefits = 
* Unified Communication Platform: Integrates contact forms, support, chat, CRM into a single, efficient workflow.
* Enhanced Multimedia Support: Users can send messages with webcam, screen, and microphone recordings, enhancing interactive support.
* Customizable Contact Forms: Tailor contact forms with custom fields to collect specific information needed for various departments.
* Advanced Conversation Management: Manage and monitor conversations from both frontend and backend, improving service responsiveness.
* Email Confirmation for Security: Protects against spam by requiring email confirmation from visitors before starting conversations.
* Multilanguage Support: Offers real-time translation of messages with DeepL integration, catering to a global audience.
* Monetization Options: Integrates with the MicroPayments Plugin for paid support options, allowing for flexible revenue models.
* Comprehensive Reporting Tools: Easily report and manage content directly from posts with configurable buttons, enhancing content control.

= Features =
* Support pages in website frontend for starting/accessing conversations
* Members can open a conversation directly, while visitors have to confirm their email first
* Admins can see and access open Conversations from backend
* Report Content button for any post types (configurable)
* Contact Owner button for any post types (configurable)
* Contact Creator in BuddyPress/BuddyBoss profile for configurable roles
* My Conversations page to website frontend, to access own tickets by logged in users
* Custom support departments for website / report content / contact content owner / contact user
* Floating support buttons (contact, new messages) on all pages (can be disabled)
* Message counter in backend (site support) and frontend (for creators)
* Send webcam/screen/microphone recordings and file uploads in conversations
* Custom contact forms with custom fields (text, dropdown, toggle) and data saved per contact
* Custom form per department enables custom fields per conversation (ticket)
* Invite by email to fill a form, contact a department or join a new conversation
* Can messages, add to later messages for quick replies
* Integrates with [MicroPayments Plugin](https://wordpress.org/plugins/paid-membership/ "MicroPayments – Paid Author Subscriptions, Digital Assets, Downloads, Membership") for paid support (per conversation, message)
* Registration forms:  register accounts in an external MySQL database, in example for [VideoWhisper WebRTC server](https://github.com/videowhisper/videowhisper-webrtc "VideoWhisper WebRTC Server")
* Multilanguage conversations with DeepL integration for translating messages on request


More features to come ...

== Screenshots ==
1. Conversation (Ticket) View with Insert Files, Webcam/MicroPhone/Screen recordings
2. Custom Form with custom fields: text, dropdown, toggle
3. Buttons on content page
4. Report content support form
5. Floating support button on all pages

== Frequently Asked Questions ==

= How does system prevent bulk submissions from "visitor" bots? =

A visitor needs to register a contact and confirm email to create a new ticket.

= How are tickets secured ? =

Each contact (email) has a code to access ticket.

== License ==

This plugin is released under a GPL license.

== Changelog ==

= 1.10 =
* Google tracking gtag for main events: contact view/new/confirm, conversation view/new, message new, form view/submit 
* Tags

= 1.9 =
* Option to allow visitors start conversation before confirming contact (resulting in pending conversation)
* Counters for new and pending conversations in conversation list
* Support can see how many conversations a contact openeded and browse these
* Filter conversation list by status, department, contact
* Sort conversation list by status, created, updated

= 1.8 = 
* Add/Remove contacts from conversations
* Reply to messages in tickets
* Set custom conversation/message price for MicroPayments support with videowhisper_support_micropayments
* Unsupported email provider domains (disable domains with email issues or common for spammers)
* Access contact fields from backend conversation view, by clicking contact

= 1.7 = 
* Multilanguage conversations (each message can be in different language)
* DeepL integration for translating messages on request

= 1.6 = 
* Create new converation for specific contact (registered or not registered) and send notification to join
* Invite by email to fill form, open conversation with specific department
* Sort contacts by last form update
* Canned messages/responses

= 1.5 = 
* Insert audio/video recordings in conversations: webcam, screen, microphone
* Insert file uploads in conversations

= 1.4 =
* Custom fields can be added to departments and filled when starting conversations or associated to contacts with forms
* Link parsing in conversation messages & custom fields
* Forms enable contacts to fill and update contact fields

= 1.3 =
* MicroPayments integration for paid support: clients pay on request and providers when they answer
* Conversation management (close, delete)

= 1.2 =
* Custom support departments for website / report content / contact content owner / contact user
* Integrate Report Content for any post types
* Integrate Contact Owner for any post types
* Contact Creator in BuddyPress/BuddyBoss profile for configurable roles
* Floating support buttons (contact, new messages) on all pages (can be disabled)
* New message counter in backend (site support) and frontend (for creators)

= 1.1 =
* First release