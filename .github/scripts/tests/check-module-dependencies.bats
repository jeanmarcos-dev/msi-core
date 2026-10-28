#!/usr/bin/env bats

setup() {
    REPO="$BATS_TEST_TMPDIR/repo"
    SCRIPT="$BATS_TEST_DIRNAME/../check-module-dependencies.php"
    module InventoryFoo magento/module-inventory-foo '"magento/framework": "*", "magento/module-catalog": "*"'
}

module() {
    mkdir -p "$REPO/$1/Model" "$REPO/$1/etc"
    echo '<?php' > "$REPO/$1/registration.php"
    echo "{\"name\": \"$2\", \"require\": {$3}}" > "$REPO/$1/composer.json"
}

php_file() {
    printf '<?php\nnamespace Magento\\InventoryFoo\\Model;\n\n%s\n' "$2" > "$REPO/InventoryFoo/$1"
}

@test "passes when every Magento class a module uses comes from a declared package" {
    php_file Model/Foo.php 'use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\InventoryFoo\Model\Bar;'

    run php "$SCRIPT" "$REPO"

    [ "$status" -eq 0 ]
    [[ "$output" == *"Every module declares the modules it uses."* ]]
}

@test "fails and names the module, the package and the file when a PHP import is undeclared" {
    php_file Model/Foo.php 'use Magento\InventoryShippingAdminUi\Model\Bar;'

    run php "$SCRIPT" "$REPO"

    [ "$status" -eq 1 ]
    [[ "$output" == *"magento/module-inventory-foo uses magento/module-inventory-shipping-admin-ui without declaring it (InventoryFoo/Model/Foo.php)"* ]]
}

@test "finds a fully qualified reference in code" {
    php_file Model/Foo.php 'class Foo { public function a() { return \Magento\Sales\Model\Order::STATE_NEW; } }'

    run php "$SCRIPT" "$REPO"

    [ "$status" -eq 1 ]
    [[ "$output" == *"uses magento/module-sales without declaring it"* ]]
}

@test "finds a class named in an XML file" {
    echo '<config><type name="Magento\GraphQl\Model\Query"><plugin name="p" type="Magento\InventoryFoo\Plugin"/></type></config>' > "$REPO/InventoryFoo/etc/di.xml"

    run php "$SCRIPT" "$REPO"

    [ "$status" -eq 1 ]
    [[ "$output" == *"uses magento/module-graph-ql without declaring it (InventoryFoo/etc/di.xml)"* ]]
}

@test "ignores comments, tests and packages declared as suggestions" {
    php_file Model/Foo.php '/** @see \Magento\Sales\Model\Order */
// Magento\Quote\Model\Quote
use Magento\Wishlist\Model\Item;'
    mkdir -p "$REPO/InventoryFoo/Test/Unit"
    printf '<?php\nuse Magento\\Customer\\Model\\Customer;\n' > "$REPO/InventoryFoo/Test/Unit/FooTest.php"
    echo '{"name": "magento/module-inventory-foo", "require": {"magento/module-catalog": "*"}, "suggest": {"magento/module-wishlist": "*"}}' > "$REPO/InventoryFoo/composer.json"

    run php "$SCRIPT" "$REPO"

    [ "$status" -eq 0 ]
}

@test "checks every module and reports each undeclared package once per module" {
    module InventoryBar magento/module-inventory-bar '"magento/framework": "*"'
    printf '<?php\nuse Magento\\Store\\Model\\Store;\nuse Magento\\Store\\Api\\StoreRepositoryInterface;\n' > "$REPO/InventoryBar/Model/Bar.php"
    php_file Model/Foo.php 'use Magento\Eav\Model\Config;'

    run php "$SCRIPT" "$REPO"

    [ "$status" -eq 1 ]
    [ "$(grep -c 'magento/module-inventory-bar uses magento/module-store' <<< "$output")" -eq 1 ]
    [[ "$output" == *"magento/module-inventory-foo uses magento/module-eav without declaring it"* ]]
}
