node{
    stage ('Prepear Env') {
        git branch: '$BRANCH_NAME', url: 'git@gitlab.sysbind.biz:Developers/moe-alt.git'
        sh 'cp config-dist.php config.php'
        sh 'sed -i -e "s%= \'moodle\'%= \'$JOB_BASE_NAME$BUILD_ID\'%" -e "s%= \'password\'%= \'1234\'%" config.php'
        sh 'sed -i -e "s%http:/p/example.com/moodle%http://localhost%" -e "s%/home/example/moodledata%/home/jenkins-slave/workspace/$JOB_BASE_NAME/$BRANCH_NAME/target/moodledata%" config.php'
        sh 'sed -i -e "s%= \'username\'%= \'postgres\'%" config.php'
        sh 'sed -i -e "s%= \'http://example.com/moodle\'%= \'http://sysbind.test\'%" config.php'
        sh 'sed -i -e "s%= \'localhost\'%= \'postgres-server\'%" config.php'
        sh 'mkdir -p /home/jenkins-slave/workspace/$JOB_BASE_NAME/$BRANCH_NAME/target/moodledata/phpunit_$BUILD_ID'
        sh 'sed -i -e "/require_once/i \\\\\\$CFG->phpunit_dataroot = \'\\/home\\/jenkins-slave\\/workspace\\/$JOB_BASE_NAME\\/$BRANCH_NAME\\/target\\/moodledata\\/phpunit_$BUILD_ID\';" -e "/require_once/i \\\\\\$CFG->phpunit_prefix = \'p_\';" config.php'
        sh 'composer config -g github-oauth.github.com ee71f264114584932339a01e0433ee0e15e82f12'
        sh 'composer install --prefer-source'
        sh 'psql -h postgres-server -U postgres -c \'CREATE DATABASE "\'$JOB_BASE_NAME\'\'$BUILD_ID\'" WITH OWNER = postgres;\' postgres'
        sh 'chmod +x filter/tex/mimetex.darwin'
    }
    
    stage ('Run UnitTest') {
        sh 'php admin/tool/phpunit/cli/init.php'
        sh 'vendor/bin/phpunit --log-junit results/phpunit/phpunit.xml || true'
        step([$class: 'JUnitResultArchiver', testResults: '**/phpunit/phpunit.xml,**/behatlog/*.xml'])
    }
    
    stage ('Post Testing') {
        sh 'psql -h postgres-server -U postgres -c \'DROP DATABASE "\'$JOB_BASE_NAME\'\'$BUILD_ID\'"; \' postgres'
    }
}