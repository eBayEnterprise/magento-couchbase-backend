![eBay Enterprise](static/logo-vert.png)

**Magento Retail Order Management Extension**
# Frequently Asked Questions

The intended audience for this guide is Magento system integrators and merchants. You should review the [Magento Retail Order Management Extension Overview](OVERVIEW.md) and [Installation and Configuration Guide](INSTALL.md) before proceeding.

## Questions

- [Do all option values for Drop Down and Multiselect Product Attributes need to be created in Magento before the Item Master is imported?](#q1)
- [My Magento implementation will use a third party extension to calculate flat rates and handle shipping restrictions. How will the Magento shipping methods need to be mapped to ROM shipping methods?](#q2)
- [Does the extension already handle Address Validation messaging display in checkout and customer address book?](#q3)
- [Is tax calculated on shipping amount? Does this require any configuration on the Magento side?](#q4)
- [Should we use client-side encryption for credit card transactions?](#q5)
- [Are Gift Card PINs supported?](#q6)
- [Is extension compatible with Magento native Catalog and Shopping Cart Price Rules, including free shipping rules?](#q7)

## Answers

<a name="q1"></a>
### Do all option values for Drop Down and Multiselect Product Attributes need to be created in Magento before the Item Master is imported?

General Case: Yes.

Special Cases:

- **Color**: When colors are imported, if a color option with an admin label matching the `ExtendedAttributes/ColorAttributes/Color/Code` already exists, that color option will be reused for the product. When a new `Color/Code` is encountered, a new option will be created for the color. The `ExtendedAttributes/ColorAttributes/Color/Description` will be used as the Store View specific label for color option and will be applied to any Store Views that are configured with a Store Language Code matching the `xml:lang` attribute of this node.
- **Size**: When sizes are imported, if a size option with an admin label matching the `ExtendedAttributes/SizeAttributes/Size/Code` already exists, that color option will be reused for the product. When a new `Size/Code` is encountered, a new option will be created for the size. The `ExtendedAttributes/SizeAttributes/Size/Description` will be used as the Store View specific label for size option and will be applied to any Store Views that are configured with a Store Language Code matching the `xml:lang` attribute of this node.

<a name="q2"></a>
### My Magento implementation will use a third party extension to calculate flat rates and handle shipping restrictions. How will the Magento shipping methods need to be mapped to ROM shipping methods?

Magento Shipping methods should be mapped to ROM shipping codes in configuration XML. An example map is included with [`rom.xml.sample`](/src/app/etc/rom.xml.sample). Node names must match Magento shipping methods. Values must match ROM shipping codes specific to the merchant implementation.

Example:

```xml
	<eb2ccore>
		<shipmap>
			<flatrate_flatrate>ANY_STD</flatrate_flatrate>
		</shipmap>
	</eb2ccore>
```

<a name="q3"></a>
### Does the extension already handle Address Validation messaging display in checkout and customer address book?

Yes. Generally speaking, the system integrator is responsible for the webstore user interface. However, there are a few instances where a base user interface is required for the Magento ROM Extension to function. In those cases, including Address Validation, a base user interface has been provided by the extension. Depending on brand/design requirements, the system integrator may be able to use the provided user interface, modify it slightly or develop a completely new one.

<a name="q4"></a>
### Is tax calculated on the shipping amount? Does this require any configuration on the Magento side?

Yes, tax is calculated on the shipping amount.

The only required configuration is to ensure there is a shipping Tax Class defined in local configuration. The sample map may be used verbatim from [`rom.xml.sample`](/src/app/etc/rom.xml.sample):

```xml
	<ebayenterprise_tax>
		<tax_class>
			<!-- tax class to use for the shipping price group -->
			<shipping>93000</shipping>
		</tax_class>
	</ebayenterprise_tax>
```

<a name="q5"></a>
### Should we use client-side encryption for credit card transactions?

Client-side encryption ensures that the Magento website never has access to a raw credit card number at rest or in transit. A website’s access to raw credit card numbers is a determining factor for which Self-Assessment Questionnaire (SAQ) the merchant will qualify under.

- If the goal is for the merchant to self assess as an SAQ A-EP merchant under PCI DSS version 3, then client-side encryption must be enabled.
- If the merchant will self asses as a SAQ D merchant under PCI DSS version 3, then client-side encryption is optional.

<a name="q6"></a>
### Are Gift Card PINs supported?

Yes, they are required.

<a name="q7"></a>
### Is extension compatible with Magento native Catalog and Shopping Cart Price Rules, including free shipping rules?

Yes.

- - -
Copyright © 2015 eBay Enterprise, Inc.
