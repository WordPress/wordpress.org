# CSS Structure

This loosely follows [ITCSS](https://www.xfive.co/blog/itcss-scalable-maintainable-css-architecture/) in folder structure, and [Gutenberg's CSS naming scheme](https://github.com/WordPress/gutenberg/blob/trunk/docs/contributors/code/coding-guidelines.md) for class names.

Generally components will use `pattern-{kebab-case-component}` for the top-level export, and `pattern-{kebab-case-component}__{child-element}` for any children classes. Conditional classes, for loading state or active state, should use an `is-` or `has-` prefix.

## 01 Settings

Typography, colors, any spacing variables, etc should be set here.

## 02 Tools

This contains any mixins. We inherit the following libraries:

- breakpoint
- kube
- modular-scale

## 03 Generic

Any generic styles. Used for normalize & reset styles. We inherit:

- kube
- normalize

## 04 Base (aka Elements)

Styles for plain html elements. We inherit the base theme's styling here.

## 05 Objects

These are pieces of UI. These should be self-contained (or nested so that they are self-contained). Blocks should
be defined here.

## 06 Components

This section puts together the base and objects to create pages. Page-specific styles are defined here.

## 07 Utilities

The `is-*`/`has-*` classes, these custom classes override previous styles. For example, `has-background` would be
defined here. This is where block styles should live.

# Editor Styles

Editor styles will use a custom import of a subset of the above folders, TBD?
