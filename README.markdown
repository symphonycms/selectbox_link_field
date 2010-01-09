# Select Box Link Field #

This is an extension for Symphony linking two sections together.

- Version: 1.15dev
- Date: **unreleased**
- Requirements: Symphony 2.0.x
- Author: Symphony Team, team@symphony-cms.com
- Constributors: A list of contributors can be found in the [commit history](http://github.com/pointybeard/selectbox_link_field/commits/master)
- GitHub Repository: <http://github.com/pointybeard/selectbox_link_field>

## Synopsis

This extension offers a simple way to link data. Add this field to your section, tell it which other section you want to link it to and you will get a drop box with all those entries to choose from. Linking is performed via the System ID value, so the link remains even when the linked entry’s field changes its value, a common problem with just using the standard Select box field with dynamic options.

Visit the related forum thread at <http://symphony-cms.com/discuss/thread/473/>.

### Notes

Setting an instance of the field to be not required will cause an empty option to show up on the publish form.

## Installation & Updating

Information about [installing and updating extensions](http://symphony-cms.com/learn/tasks/view/install-an-extension/) can be found in the Symphony documentation at <http://symphony-cms.com/learn/>.

## Change Log

#### Version 1.15

- Added missing translation strings
- Added localisation files for Dutch, German, Portuguese (Brazil) and Russian 

#### Version 1.14	

- Made install and update functions more tolerant of existing tables
- Minor bug fixes for 2.0.7
		
#### Version 1.13

- Added filtering by handle functionality (CreativeDutchmen)
		
#### Version 1.12

- Fixed a couple of issues where 'related_field_id' was returning the wrong type. (rowan)
- In dropdown options, sort Sections by their Symphony order and sort Entries by their Symphony order (using EntryManager) (Nick Dunn)
- Sort Sections in field's settings panel by Symphony order (Nick Dunn)
		
#### Version 1.11

- Fixed bug that triggered a database error in Symphony version greater than 2.0.6

### Version 1.10

- Added translations
- Possible to toggle values via publish tables
		
#### Version 1.9

- Warnings about incorrect data type, origination from line 409, are now suppressed
- Fixed sorting to work when "random" is selected
		
#### Version 1.8

- Fixed bug that caused no items to appear selected in publish area
		
#### Version 1.7

- Updated fetchAssociatedEntrySearchValue() to make use of entry id passed in, if available

		
#### Version 1.6

- Fixed problems with updating from a version earlier than 1.4

#### Version 1.5

- Added a limit to the number of entries shown in select box
- Allowed selection of multiple source sections
		
#### Version 1.4

- Enable Data Source param output for this field
		
#### Version 1.3

- Fixed bug introduced in 1.2 that caused table values to disappear if the first field of the section is a "Select Box Link".
		
#### Version 1.2

- Should correctly work with fields that do now use a 'value' column in the database. This would cause an empty select box.
		
#### Version 1.1

- Added ability to set field to required/not required.
- Added multi-select property (thanks to czheng)
