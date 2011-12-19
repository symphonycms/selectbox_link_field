# Select Box Link Field

- Version: 1.22
- Author: Symphony Team (team@symphony-cms.com)
- Build Date: 4th November 2011
- Requirements: Symphony 2.2 or greater


## Installation

1. Upload the `selectbox_link_field` folder in this archive to your Symphony 'extensions' folder.

2. Enable it by selecting the "Field: Select Box Link", choose Enable from the with-selected menu, then click Apply.

3. You can now add the "Select Box Link" field to your sections.

## Updating

1. Be sure to visit the Extension page in the Symphony admin and
   enable "Select Box Link Field" so the database is updated accordingly.

## Usage

- Works in a near identical way to the standard select box field, however there is no static options and entries are linked internally via their ID, meaning that if an entry is changed, any Select Box Link fields will not lose their link to that entry. The data on the front-end is presented in a way identical to that of a Section Link.

- Setting an instance of the field to be not required will cause an empty option to show up on the publish form.
