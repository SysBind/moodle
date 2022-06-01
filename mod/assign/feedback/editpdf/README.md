# Assign Feedback Edit PDF plugin
## Requirements
### OS packages
- textlive-full
- imagick
- ghostscript

### PHP extensions
- imagick

### Moodle Plugins
- editor_atto
- filter_tex
- filter_mathjaxloader

## Enable Plugin
1. Enable Atto Editor
1. Enable filter_tex
1. Enable filter_mathjaxloader

## Enable gosthscript create images
1. Open terminal and edit the file /etc/ImageMagick-6/policy.xml, remark or delete all lines of gostscript format type
   ```xml
      <policy domain="path" rights="none" pattern="@*"/>
      <!-- disable ghostscript format types -->
      <policy domain="coder" rights="none" pattern="PS" /> 
      <policy domain="coder" rights="none" pattern="PS2" /> 
      <policy domain="coder" rights="none" pattern="PS3" /> 
      <policy domain="coder" rights="none" pattern="EPS" />
   ```
