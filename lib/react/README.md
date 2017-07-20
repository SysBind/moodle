# React N JSX Support for Moodle

## React JS Support

to use inside AMD module:

``` js
define(['react', 'reactdom'], function(React, ReactDOM) {

// Use React here, define your module
// see https://docs.moodle.org/dev/Javascript_Modules

}

```


## JSX Support

will process any '**/amd/src/*.jsx' to corresponding '**/amd/src/*.js'

if you have some 'local/component/amd/src/comp.jsx'

``` jsx
class Hello extends React.Component {
    render() {
	return <div>Hello {this.props.toWhat}</div>;
    }
}
```

Then running ```grunt jsx``` or just ```grunt```
will generate the following 'local/component/amd/src/comp.js:

``` js
class Hello extends React.Component {
	render() {
		return React.createElement(
			"div",
			null,
			"Hello ",
			this.props.toWhat
		);
	}
}
```

